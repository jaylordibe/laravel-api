#!/usr/bin/env bash
#
# Runtime-mode entrypoint for the production image.
#
# The base image (jaylordibe/laravel-php) runs whatever command it is given: its
# web stack (supervisord → nginx + php-fpm + Horizon) by default, or a single
# command as www-data and PID 1. Left as-is, Horizon rides along in the web stack —
# correct for a single-container development box and wrong for anything that
# scales the API horizontally: every additional API replica would also add a
# Horizon master, so queue concurrency would become a function of web traffic.
#
# So this script prepares the application, then hands the base entrypoint the
# command for the selected runtime:
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
# The privilege drop, the PID-1 handover and the ownership fix for storage/ and
# bootstrap/cache all belong to the base entrypoint, and are not repeated here.
#
# The application code is identical in every mode. Nothing here is
# provider-specific: a platform picks a mode by setting one environment variable
# or overriding the command, which every container runtime can do.
set -euo pipefail

APP_DIR=/var/www/html
BASE_ENTRYPOINT=/entrypoint.sh
NGINX_SITE=/etc/nginx/conf.d/default.conf
RUNTIME_ENV_FILE=/run/app-runtime.env

# The base image's default command (its Dockerfile CMD). Docker resets an inherited
# CMD once this image sets its own ENTRYPOINT, so the web runtimes pass it
# explicitly. The `docker` CI job boots api and all modes for real, so a change
# to it upstream fails there rather than in production.
WEB_STACK=(/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf)

MODE="${APP_RUNTIME_MODE:-api}"

# Set only by resolve_ports; empty in the runtimes that serve no HTTP.
HTTP_PORT=''
FPM_PING_PORT=''

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

    # Validated like PORT: nginx is configured with it.
    case "$FPM_PING_PORT" in
        ''|*[!0-9]*) die "APP_FPM_PING_PORT must be a number, got '${FPM_PING_PORT}'." ;;
    esac

    if [ "$FPM_PING_PORT" -lt 1 ] || [ "$FPM_PING_PORT" -gt 65535 ]; then
        die "APP_FPM_PING_PORT out of range: ${FPM_PING_PORT}."
    fi

    if [ "$FPM_PING_PORT" = "$HTTP_PORT" ]; then
        # 8081, or the next port when PORT is 8081 itself — the two must never be equal.
        FPM_PING_PORT=8081
        [ "$FPM_PING_PORT" != "$HTTP_PORT" ] || FPM_PING_PORT=8082
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

# Read back by docker/healthcheck.sh, as data. Written at container start into /run,
# so it never exists in an image layer.
publish_runtime_env() {
    {
        echo "APP_RUNTIME_MODE=${MODE}"
        echo "APP_HTTP_PORT=${HTTP_PORT:-}"
        echo "APP_FPM_PING_PORT=${FPM_PING_PORT:-}"
    } > "$RUNTIME_ENV_FILE"
}

# ---------------------------------------------------------------------------
# Startup artisan runs as www-data
# ---------------------------------------------------------------------------
# This script runs as root, and every artisan boot `require`s PHP from
# bootstrap/cache (packages.php, services.php, config.php) — a directory www-data
# owns and can write. Booting artisan as root here would let anything that ever
# gained www-data code execution plant PHP that root runs on the next container
# start. So the startup artisan steps run through the base image's as-app, the
# one place its privilege drop is written. The ownership fix runs once, in the base
# entrypoint at the final hand-off; the image's own trees are www-data's from the
# build.
artisan_as_app() {
    /usr/local/bin/as-app php artisan "$@"
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
    if ! artisan_as_app config:cache --no-interaction >/dev/null; then
        rm -f "${APP_DIR}/bootstrap/cache/config.php" 2>/dev/null || true
        log "WARNING: config:cache failed; continuing with live configuration."
        return 0
    fi

    # Only the HTTP runtimes serve routes. Caching them in `scheduler` — invoked
    # once a minute — is a second full framework boot per invocation for an
    # artefact nothing reads, and it widens the window where a failed cache write
    # leaves a half-written file behind.
    local routes_cached=false

    case "$MODE" in
        api|all)
            if artisan_as_app route:cache --no-interaction >/dev/null; then
                routes_cached=true
            else
                rm -f "${APP_DIR}"/bootstrap/cache/routes-*.php 2>/dev/null || true
                log "WARNING: route:cache failed; continuing with uncached routes."
            fi
            ;;
    esac

    # Reports what was ACTUALLY cached. A fixed "configuration and routes cached"
    # line claimed a route cache in the modes that deliberately skip one and in
    # the failure path above, which is precisely the log an operator reads while
    # trying to work out why routing is slow.
    if [ "$routes_cached" = 'true' ]; then
        log "configuration and routes cached from the runtime environment."
    else
        log "configuration cached from the runtime environment; routes are not cached in this runtime."
    fi
}

# ---------------------------------------------------------------------------
# Fail-fast configuration check
# ---------------------------------------------------------------------------
# Runs against the EFFECTIVE configuration (after caching above), for the
# long-running modes only. The one-shot modes are deliberately exempt so an
# operator can still exec into a misconfigured deployment and run artisan.
check_configuration() {
    [ "${APP_CONFIG_CHECK:-true}" = 'true' ] || return 0

    artisan_as_app app:check-config --no-interaction \
        || die "configuration check failed; refusing to start. Set APP_CONFIG_CHECK=false to override."
}

# Hands the command to the base entrypoint, which fixes ownership of storage/ and
# bootstrap/cache, then either starts the web stack (supervisord) or execs the
# command as www-data and PID 1 — so SIGTERM from the container runtime reaches it
# directly, with no shell in between to swallow it.
#
# APP_ENTRYPOINT_DRY_RUN is a TEST SEAM, not a runtime feature. It performs every
# preparation step — port rewriting, cache warming — and then reports the hand-off
# instead of making it, so CI can assert on the resulting state of the container.
# It is never set by a deployment.
exec_base() {
    if [ "${APP_ENTRYPOINT_DRY_RUN:-false}" = 'true' ]; then
        if [ "$1" = "${WEB_STACK[0]}" ]; then
            log "DRY RUN: would start the web stack with HORIZON_ENABLED=${HORIZON_ENABLED:-true}."
        else
            log "DRY RUN: would exec as www-data: $*"
        fi
        return 0
    fi

    exec "$BASE_ENTRYPOINT" "$@"
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

# The artisan commands above ran as www-data (artisan_as_app), so what they wrote
# into bootstrap/cache is already www-data's. The base entrypoint's ownership fix
# still runs on every hand-off below, for anything else that left a root-owned file.

case "$MODE" in
    api|all)
        resolve_ports
        configure_nginx_ports
        # Re-published so the healthcheck sees the resolved ports, which are only
        # known after resolve_ports.
        publish_runtime_env

        if [ "$MODE" = 'api' ]; then
            # The base image's own switch; supervisord then never starts Horizon.
            # Set here rather than left to the platform, because an API replica
            # carrying a Horizon master is the failure this split exists to prevent.
            export HORIZON_ENABLED=false
        else
            # The reverse of api: a shared env file carrying HORIZON_ENABLED=false
            # for the web service must not leave the one-container deployment with
            # no queue worker at all.
            export HORIZON_ENABLED=true
            log "WARNING: mode 'all' runs the API and a Horizon master in ONE container."
            log "WARNING: do not scale this mode to more than one replica."
        fi

        check_configuration

        # The base image owns trusted-proxy rendering, the ownership fix and
        # supervisord. Not reimplemented here — a second copy would drift from the
        # image it is copied from.
        exec_base "${WEB_STACK[@]}"
        ;;

    worker)
        check_configuration

        # Horizon as PID 1. On SIGTERM it stops accepting new jobs, lets the
        # in-flight ones finish, and exits — which is precisely the graceful
        # shutdown a container platform's termination grace period is for.
        log "starting Horizon."
        exec_base php artisan horizon
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
        exec_base php artisan schedule:run --no-interaction
        ;;

    migrate)
        # An independent runtime command, never wired into api/worker/scheduler
        # startup. Deployment platforms invoke it as their own step so migrations
        # run exactly once per release instead of once per replica.
        log "applying database migrations."
        exec_base php artisan migrate --force --no-interaction
        ;;

    artisan)
        [ "$#" -gt 0 ] || die "mode 'artisan' requires a command, e.g. \`artisan horizon:terminate\`."

        log "running: artisan $*"
        exec_base php artisan "$@"
        ;;
esac
