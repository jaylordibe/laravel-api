#!/usr/bin/env bash
#
# Mode-aware container healthcheck.
#
# The base image ships a HEALTHCHECK that curls php-fpm's ping endpoint. That is
# right for the API and wrong for every other runtime: a worker container has no
# nginx, so the inherited check would mark a perfectly healthy Horizon master
# unhealthy and, on platforms that act on that, restart it in a loop.
#
# This is the CONTAINER-level check. It is deliberately not the same thing as the
# application's readiness endpoint: this one answers "is the process alive", while
# /api/health/ready answers "can it serve requests", which includes dependencies
# it does not own. Conflating them makes a database blip restart every container.
set -uo pipefail

RUNTIME_ENV_FILE=/run/app-runtime.env

# Written by docker/entrypoint.sh, so the probe targets the port that was actually
# configured rather than re-deriving it and drifting.
# shellcheck disable=SC1090
[ -r "$RUNTIME_ENV_FILE" ] && . "$RUNTIME_ENV_FILE"

MODE="${APP_RUNTIME_MODE:-api}"

case "$MODE" in
    api|all)
        exec curl -fsS -o /dev/null --max-time 4 \
            "http://127.0.0.1:${APP_FPM_PING_PORT:-8080}/fpm-ping"
        ;;

    worker)
        # `horizon:status` exits 0 running, 1 paused, 2 inactive. A PAUSED master
        # is treated as healthy on purpose: pausing is an operator action, and a
        # healthcheck that restarts the container would silently undo it.
        output="$(php /var/www/html/artisan horizon:status --no-interaction 2>&1)"
        status=$?

        case "$status" in
            0|1) exit 0 ;;
            *)
                echo "$output" >&2
                exit 1
                ;;
        esac
        ;;

    *)
        # scheduler / migrate / artisan are one-shot. Their exit code is the
        # result; there is no steady state to probe.
        exit 0
        ;;
esac
