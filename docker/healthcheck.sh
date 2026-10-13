#!/usr/bin/env bash
#
# Mode-aware container healthcheck.
#
# The base image's healthcheck pings php-fpm for its web stack and reports any
# other command healthy. This one knows the application: it follows the php-fpm
# ping port docker/entrypoint.sh may move (PORT=8080), and for the worker it checks
# that this container's Horizon master is actually alive.
#
# This is the CONTAINER-level check. It is deliberately not the same thing as the
# application's readiness endpoint: this one answers "is the process alive", while
# /api/health/ready answers "can it serve requests", which includes dependencies
# it does not own. Conflating them makes a database blip restart every container.
set -uo pipefail

RUNTIME_ENV_FILE=/run/app-runtime.env

# The ping port docker/entrypoint.sh actually configured (it moves when PORT=8080),
# rather than re-deriving it and drifting. Read as data, never sourced: this runs as
# root, so nothing in the file is ever executed.
FPM_PING_PORT="$(sed -n 's/^APP_FPM_PING_PORT=\([0-9][0-9]*\)$/\1/p' "$RUNTIME_ENV_FILE" 2>/dev/null)"

MODE="${APP_RUNTIME_MODE:-api}"

case "$MODE" in
    api|all)
        exec curl -fsS -o /dev/null --max-time 4 \
            "http://127.0.0.1:${FPM_PING_PORT:-8080}/fpm-ping"
        ;;

    worker)
        # This container's own Horizon master — not `horizon:status`, which reports
        # on every replica's master and so stays green while this one is wedged.
        # See app/Console/Commands/CheckWorkerCommand.php.
        #
        # Docker runs healthchecks as root; the base image's as-app drops to
        # www-data (and leaves an unprivileged container as it is).
        output="$(/usr/local/bin/as-app php /var/www/html/artisan app:check-worker --no-interaction 2>&1)"
        status=$?

        if [ "$status" -ne 0 ]; then
            echo "$output" >&2
            exit 1
        fi
        exit 0
        ;;

    *)
        # scheduler / migrate / artisan are one-shot. Their exit code is the
        # result; there is no steady state to probe.
        exit 0
        ;;
esac
