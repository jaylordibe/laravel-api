#!/usr/bin/env bash
#
# Runtime-mode entrypoint for the production image.
#
# The base image (jaylordibe/laravel-php) has ONE entrypoint that always execs
# supervisord, and its supervisord.conf starts nginx, php-fpm AND
# `php artisan horizon` together. That is correct for a single-container
# development box and wrong for anything that scales the API horizontally: every
# additional API replica would also add a Horizon master, so queue concurrency
# would become a function of web traffic, and `horizon:terminate` during a deploy
# would race across replicas.
#
# So this script sits in front of the base entrypoint and selects a runtime:
#
#   APP_RUNTIME_MODE=api        nginx + php-fpm only              (long-running, DEFAULT)
#   APP_RUNTIME_MODE=worker     php artisan horizon               (long-running)
#   APP_RUNTIME_MODE=scheduler  php artisan schedule:run          (one-shot, exits)
#   APP_RUNTIME_MODE=migrate    php artisan migrate --force       (one-shot, exits)
#   APP_RUNTIME_MODE=artisan    php artisan "$@"                  (one-shot, exits)
#   APP_RUNTIME_MODE=all        nginx + php-fpm + horizon         (single-container only)
#
# `all` reproduces the base image's behaviour and exists for a one-box Docker
# Compose or single-VM deployment. It is NOT for a platform that scales replicas.
#
# The application code is identical in every mode. Nothing here is
# provider-specific: a platform picks a mode by setting one environment variable
# or overriding the command, which every container runtime can do.
set -euo pipefail

APP_DIR=/var/www/html
BASE_ENTRYPOINT=/entrypoint.sh
SUPERVISOR_CONF=/etc/supervisor/conf.d/supervisord.conf
NGINX_SITE=/etc/nginx/conf.d/default.conf
RUNTIME_ENV_FILE=/run/app-runtime.env
RUN_AS=www-data

MODE="${APP_RUNTIME_MODE:-api}"

cd "$APP_DIR"

log() { echo "app-entrypoint[${MODE}]: $*" >&2; }
die() { log "FATAL: $*"; exit 1; }

# ---------------------------------------------------------------------------
# Port contract
# ---------------------------------------------------------------------------
# The base image's nginx serves the application on :80 and, separately, binds
# 127.0.0.1:8080 for php-fpm's /fpm-ping and /fpm-status. Both are rewritten here
# so the container can answer on whatever port the platform dictates.
#
# PORT is honoured because several managed container runtimes inject it, but 80
# stays the DEFAULT: hard-coding 8080 "because one cloud uses it" would silently
# change the port contract for every deployment that does not.
#
# When PORT collides with the fpm listener, the fpm listener moves rather than
# the application: an operator chose the application's port, nothing chose the
# diagnostic one. The resolved values are written to RUNTIME_ENV_FILE so the
# healthcheck probes the same port this script configured, instead of guessing.
resolve_ports() {
    HTTP_PORT="${PORT:-${APP_HTTP_PORT:-80}}"

    case "$HTTP_PORT" in
        ''|*[!0-9]*) die "PORT must be a number, got '${HTTP_PORT}'." ;;
    esac

    if [ "$HTTP_PORT" -lt 1 ] || [ "$HTTP_PORT" -gt 65535 ]; then
        die "PORT out of range: ${HTTP_PORT}."
    fi

    FPM_PING_PORT="${APP_FPM_PING_PORT:-8080}"

    if [ "$FPM_PING_PORT" = "$HTTP_PORT" ]; then
        FPM_PING_PORT=8081
        log "PORT=${HTTP_PORT} collides with the php-fpm ping listener; moving it to ${FPM_PING_PORT}."
    fi
}

# nginx cannot read environment variables, so the ports are rewritten into its
# configuration before supervisord starts it. Anchored on the exact directives the
# base image ships; if a future base image changes them, the assertion below fails
# loudly rather than serving on an unexpected port.
configure_nginx_ports() {
    [ -f "$NGINX_SITE" ] || die "expected nginx site config at ${NGINX_SITE}."

    sed -i \
        -e "s/^\([[:space:]]*\)listen[[:space:]]\+80;/\1listen ${HTTP_PORT};/" \
        -e "s/^\([[:space:]]*\)listen[[:space:]]\+\[::\]:80;/\1listen [::]:${HTTP_PORT};/" \
        -e "s/^\([[:space:]]*\)listen[[:space:]]\+127\.0\.0\.1:8080;/\1listen 127.0.0.1:${FPM_PING_PORT};/" \
        "$NGINX_SITE"

    grep -qE "^[[:space:]]*listen[[:space:]]+${HTTP_PORT};" "$NGINX_SITE" \
        || die "could not set the application listener to ${HTTP_PORT} in ${NGINX_SITE}."
    grep -qE "^[[:space:]]*listen[[:space:]]+127\.0\.0\.1:${FPM_PING_PORT};" "$NGINX_SITE" \
        || die "could not set the php-fpm ping listener to ${FPM_PING_PORT} in ${NGINX_SITE}."

    log "serving HTTP on 0.0.0.0:${HTTP_PORT} (php-fpm ping on 127.0.0.1:${FPM_PING_PORT})."
}

# Read back by docker/healthcheck.sh. /run is a tmpfs, so this never persists
# into an image layer.
publish_runtime_env() {
    {
        echo "APP_RUNTIME_MODE=${MODE}"
        echo "APP_HTTP_PORT=${HTTP_PORT:-}"
        echo "APP_FPM_PING_PORT=${FPM_PING_PORT:-}"
    } > "$RUNTIME_ENV_FILE"
}

# ---------------------------------------------------------------------------
# Horizon supervision
# ---------------------------------------------------------------------------
# In `api` mode the base image's horizon program is switched off. autostart=false
# is used rather than deleting the block so `supervisorctl start horizon` is still
# available for one-off debugging inside a running container.
#
# The awk rewrite emits autostart=false immediately after the section header and
# drops any existing autostart line inside that block, which covers both a block
# that sets autostart and one that relies on supervisord's default of true.
disable_supervisor_program() {
    local program="$1"
    local target="[program:${program}]"
    local rendered

    # FATAL, not a shrug. The base image is pinned to a floating tag
    # (`jaylordibe/laravel-php:8.5`), so it can be rebuilt with the program
    # renamed and no diff here. Returning 0 on absence would mean an API replica
    # silently regains a Horizon master — the exact failure this whole runtime
    # split exists to prevent, and one that produces no error anywhere.
    if ! grep -qF "$target" "$SUPERVISOR_CONF"; then
        die "expected ${target} in ${SUPERVISOR_CONF}. The base image changed; api mode cannot confirm Horizon is disabled."
    fi

    rendered="$(awk -v target="$target" '
        /^\[/ {
            inside = ($0 == target)
            print
            if (inside) {
                print "autostart=false"
            }
            next
        }
        inside && /^[[:space:]]*autostart[[:space:]]*=/ { next }
        { print }
    ' "$SUPERVISOR_CONF")"

    printf '%s\n' "$rendered" > "$SUPERVISOR_CONF"

    # Proving the edit landed matters more than usual: silently failing here is
    # exactly the bug this script exists to prevent — a Horizon master in every
    # horizontally scaled API replica.
    local effective
    effective="$(awk -v target="$target" '
        /^\[/ { inside = ($0 == target) }
        inside && /^[[:space:]]*autostart[[:space:]]*=/ { gsub(/[[:space:]]/, "", $0); print; exit }
    ' "$SUPERVISOR_CONF")"

    [ "$effective" = 'autostart=false' ] \
        || die "could not disable the ${program} supervisor program (got '${effective:-nothing}')."

    log "supervisor program '${program}' disabled for this runtime."
}

# ---------------------------------------------------------------------------
# Writable paths
# ---------------------------------------------------------------------------
# The base entrypoint does this for the modes that reach it. The modes that
# bypass supervisord need it too, because they also run as www-data.
ensure_writable() {
    local path

    for path in "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" ${EXTRA_WRITABLE_PATHS:-}; do
        [ -d "$path" ] || continue

        if ! find "$path" ! -user "$RUN_AS" -exec chown "${RUN_AS}:${RUN_AS}" {} + 2>/dev/null; then
            log "WARNING: could not take ownership of ${path} for ${RUN_AS}."
        fi
    done
}

# ---------------------------------------------------------------------------
# Configuration caching — at RUNTIME, never at build
# ---------------------------------------------------------------------------
# `php artisan config:cache` evaluates every env() call and writes the RESULT to
# bootstrap/cache/config.php. Laravel then loads that file and skips .env and the
# config files entirely, so the values frozen at cache time are final.
#
# Running it during `docker build` therefore freezes build-time values — which,
# because .dockerignore excludes .env, are the defaults in config/*.php. The image
# would ship a config.php saying DB_HOST=127.0.0.1, and every DB_HOST the platform
# injects at runtime would be silently ignored. That failure is invisible in a
# passing test suite and a green image build; it only appears as a production
# container that cannot reach its database.
#
# Caching here instead means the cache is built from the environment the container
# actually received, which is the only environment that is authoritative.
# NOTE THE VARIABLE NAME. It is APP_RUNTIME_CONFIG_CACHE, not APP_CONFIG_CACHE,
# and that is not cosmetic: APP_CONFIG_CACHE is RESERVED BY LARAVEL. The framework
# reads it in Application::normalizeCachePath() to override the PATH of the cached
# config file, so setting it to "false" makes Laravel resolve that path to the
# application base directory, find that `file_exists()` is true for a directory,
# and `require` it — killing every command in the container with
# "Class \"config\" does not exist". Do not rename this back.
warm_runtime_caches() {
    if [ "${APP_RUNTIME_CONFIG_CACHE:-true}" != 'true' ]; then
        # A stale cache would defeat the point, so make sure there is none.
        rm -f "${APP_DIR}/bootstrap/cache/config.php" 2>/dev/null || true
        rm -f "${APP_DIR}"/bootstrap/cache/routes-*.php 2>/dev/null || true
        log "APP_RUNTIME_CONFIG_CACHE is not 'true'; running from live configuration."
        return 0
    fi

    # A partially written config.php is worse than none: Laravel would load it and
    # ignore the environment. Remove it on failure and continue uncached, which is
    # slower but always reads the real environment.
    if ! php artisan config:cache --no-interaction >/dev/null; then
        rm -f "${APP_DIR}/bootstrap/cache/config.php" 2>/dev/null || true
        log "WARNING: config:cache failed; continuing with live configuration."
        return 0
    fi

    # Only the HTTP runtimes serve routes. Caching them in `scheduler` — invoked
    # once a minute — is a second full framework boot per invocation for an
    # artefact nothing reads, and it widens the window where a failed cache write
    # leaves a half-written file behind.
    case "$MODE" in
        api|all)
            if ! php artisan route:cache --no-interaction >/dev/null; then
                rm -f "${APP_DIR}"/bootstrap/cache/routes-*.php 2>/dev/null || true
                log "WARNING: route:cache failed; continuing with uncached routes."
            fi
            ;;
    esac

    log "configuration and routes cached from the runtime environment."
}

# ---------------------------------------------------------------------------
# Fail-fast configuration check
# ---------------------------------------------------------------------------
# Runs against the EFFECTIVE configuration (after caching above), for the
# long-running modes only. The one-shot modes are deliberately exempt so an
# operator can still exec into a misconfigured deployment and run artisan.
check_configuration() {
    [ "${APP_CONFIG_CHECK:-true}" = 'true' ] || return 0

    php artisan app:check-config --no-interaction \
        || die "configuration check failed; refusing to start. Set APP_CONFIG_CHECK=false to override."
}

# `exec` so the application process becomes PID 1 and receives SIGTERM directly
# from the container runtime — no shell in between swallowing the signal.
# --inh-caps=-all drops inheritable capabilities on the way down.
#
# APP_ENTRYPOINT_DRY_RUN is a TEST SEAM, not a runtime feature. It performs every
# preparation step — port rewriting, disabling the Horizon program, cache warming
# — and then returns instead of handing over to the long-running process, so CI
# can assert on the resulting state of the container. Without it the only way to
# check "does api mode really disable Horizon" is to boot supervisord and go
# looking, which is slow and racy. It is never set by a deployment.
exec_as_app() {
    if [ "${APP_ENTRYPOINT_DRY_RUN:-false}" = 'true' ]; then
        log "DRY RUN: would exec as ${RUN_AS}: $*"
        return 0
    fi

    exec setpriv --reuid="$RUN_AS" --regid="$RUN_AS" --init-groups --inh-caps=-all -- "$@"
}

# The api/all handover, with the same dry-run seam.
exec_base_entrypoint() {
    if [ "${APP_ENTRYPOINT_DRY_RUN:-false}" = 'true' ]; then
        log "DRY RUN: would exec ${BASE_ENTRYPOINT} (nginx + php-fpm under supervisord)."
        return 0
    fi

    exec "$BASE_ENTRYPOINT"
}

# ---------------------------------------------------------------------------
# Dispatch
# ---------------------------------------------------------------------------
# Validated FIRST, before any state is touched, so an unknown mode is a clean
# refusal rather than a container that half-configured itself and then died.
case "$MODE" in
    api|all|worker|scheduler|migrate|artisan) ;;
    *)
        die "unknown APP_RUNTIME_MODE '${MODE}'. Valid modes: api, worker, scheduler, migrate, artisan, all."
        ;;
esac

publish_runtime_env

# Cache warming runs in EVERY mode, deliberately.
#
# The alternative — caching only for the long-running runtimes — means a
# `migrate` or `artisan` container resolves configuration by a different path
# than the api container it is deployed alongside. Any divergence between those
# two paths is then a bug that appears in one runtime and not the other, which is
# among the least pleasant kinds to chase. One path, every mode.
warm_runtime_caches

# AFTER warming, not before: the artisan commands above run as root and write
# into bootstrap/cache, so an ownership fix that ran first would be undone by the
# very next line. The api/all modes get this again from the base entrypoint,
# which is harmless — the traversal only touches files whose owner is wrong.
ensure_writable

case "$MODE" in
    api|all)
        resolve_ports
        configure_nginx_ports
        # Re-published so the healthcheck sees the resolved ports, which are only
        # known after resolve_ports.
        publish_runtime_env

        if [ "$MODE" = 'api' ]; then
            disable_supervisor_program horizon
        else
            log "WARNING: mode 'all' runs the API and a Horizon master in ONE container."
            log "WARNING: do not scale this mode to more than one replica."
        fi

        check_configuration

        # Hands over to the base image, which owns trusted-proxy rendering, the
        # ownership fix and supervisord. Not reimplemented here — a second copy
        # would drift from the image it is copied from.
        exec_base_entrypoint
        ;;

    worker)
        check_configuration

        # Horizon as PID 1. On SIGTERM it stops accepting new jobs, lets the
        # in-flight ones finish, and exits — which is precisely the graceful
        # shutdown a container platform's termination grace period is for.
        log "starting Horizon."
        exec_as_app php artisan horizon
        ;;

    scheduler)
        # Deliberately a SHORT-LIVED invocation, not `schedule:work`. It runs the
        # tasks due right now and exits, so any scheduler — cron, a managed
        # scheduler service, a Kubernetes CronJob — can drive it on a one-minute
        # cadence without this image needing to know which one.
        #
        # No check_configuration here, nor in the other one-shot modes: an
        # operator must still be able to run commands against a deployment whose
        # configuration is what they are trying to diagnose.
        log "running due scheduled tasks."
        exec_as_app php artisan schedule:run --no-interaction
        ;;

    migrate)
        # An independent runtime command, never wired into api/worker/scheduler
        # startup. Deployment platforms invoke it as their own step so migrations
        # run exactly once per release instead of once per replica.
        log "applying database migrations."
        exec_as_app php artisan migrate --force --no-interaction
        ;;

    artisan)
        [ "$#" -gt 0 ] || die "mode 'artisan' requires a command, e.g. \`artisan horizon:terminate\`."

        log "running: artisan $*"
        exec_as_app php artisan "$@"
        ;;
esac
