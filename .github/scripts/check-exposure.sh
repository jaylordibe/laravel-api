#!/usr/bin/env bash
#
# External-exposure regression checks. Host-side; needs docker, curl and jq.
#
#   check-exposure.sh compose        every published port in docker-compose.yml
#                                    binds the loopback interface
#   check-exposure.sh image <tag>    the built production image, booted in `api`
#                                    mode, serves nothing over HTTP but public/
#                                    and the application, and listens on nothing
#                                    else
#
# STRUCTURAL, NOT A BLACKLIST. The image check does not hold a list of "sensitive
# paths". It enumerates every file the image actually ships outside public/ and
# requests each one, so a file added to the repository tomorrow is covered without
# editing this script. Canaries are planted where secrets live at runtime (.env,
# the Passport key, logs, the private disk, a PHP file outside the docroot) because
# those do not exist in the image and so cannot be enumerated.
#
# A status code alone proves little: a fallback route can answer 200 with a body
# that is not the file, and a 404 page can echo what it was asked for. So every
# probe also follows same-origin redirects and inspects the body — for canary
# tokens, for the file's own bytes, and for markers of source or debug output.
# Positive controls prove the harness can see a leak before it reports none.
#
# REACH: a shipped file is requested at the URL that mirrors its path, which is
# where a mis-rooted or mis-denied server exposes it. A server rule mapping the
# tree under some other prefix (an nginx `alias`) is outside what this finds.
set -euo pipefail

die() { echo "check-exposure: FAIL: $*" >&2; exit 1; }
failures=0
fail() { echo "  FAIL: $*" >&2; failures=$((failures + 1)); }

check_compose() {
    local compose_file="${1:-docker-compose.yml}"
    local resolved ports offenders host_networked profile
    local profile_args=()

    # `docker compose config` is Docker's own resolved view: short and long port
    # syntax both normalise to host_ip/published, so nothing here parses YAML.
    # Every profile is activated because a service under `profiles:` is otherwise
    # left out of the resolved view entirely, and would publish unseen. They are
    # enumerated rather than passed as `--profile '*'`: a Compose that reads `*`
    # as a literal name drops every profiled service without a word.
    while IFS= read -r profile; do
        [ -n "$profile" ] && profile_args+=(--profile "$profile")
    done < <(docker compose -f "$compose_file" config --profiles 2>/dev/null)

    # Stderr is dropped to hide the unset-variable warnings a bare checkout
    # produces, so on failure the message says how to see the real error.
    # The `+` form expands an empty array to nothing; bare "${a[@]}" aborts
    # under `set -u` on bash 3.2, which macOS ships.
    resolved="$(docker compose ${profile_args[@]+"${profile_args[@]}"} -f "$compose_file" config --format json 2>/dev/null)" \
        || die "docker compose could not resolve ${compose_file}; run \`docker compose -f ${compose_file} config\` to see why."

    # Host networking publishes every port the container opens, with no `ports:`
    # entry to inspect — so it can never pass as loopback-only.
    host_networked="$(jq -r '.services | to_entries[] | select(.value.network_mode == "host") | .key' <<<"$resolved")"
    [ -z "$host_networked" ] || die "services use network_mode: host, which exposes every port on every interface: ${host_networked//$'\n'/, }."

    ports="$(jq -c '[.services | to_entries[] | .key as $service
                     | .value.ports[]? | {service: $service, host_ip, published, target}]' <<<"$resolved")"

    [ "$(jq 'length' <<<"$ports")" -gt 0 ] || die "no published ports found in ${compose_file}; the check would be vacuous."

    offenders="$(jq -c '.[] | select(.host_ip != "127.0.0.1" and .host_ip != "::1")' <<<"$ports")"

    if [ -n "$offenders" ]; then
        echo "$offenders" >&2
        die "published ports above bind every host interface. Prefix them with 127.0.0.1: (see the header of ${compose_file})."
    fi

    echo "check-exposure: compose: $(jq 'length' <<<"$ports") published ports, all on loopback."
}

sha256_of() {
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$1" | cut -d' ' -f1
    else
        shasum -a 256 "$1" | cut -d' ' -f1
    fi
}

check_image() {
    local image="$1"
    local container="exposure-check-$$"
    local work token app=/var/www/html

    work="$(mktemp -d)"
    token="EXPOSURE_CANARY_$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"
    trap 'docker rm -f "'"$container"'" >/dev/null 2>&1 || true; rm -rf "'"$work"'"' EXIT

    # Production-representative: the real entrypoint, `api` mode, production
    # environment, debug off. There is no database or Redis here, so
    # APP_CONFIG_CHECK is off for that reason only.
    #
    # The session and cache stores ARE replaced, and that is load-bearing. With
    # the database session store unreachable, every web-group route (/, /horizon,
    # /docs, any fallback) dies in StartSession before its handler runs — so a
    # route that leaked a file would answer a harmless 500 and pass. In-memory
    # stores let every probe reach the code that decides what to return.
    docker run -d --name "$container" -p 127.0.0.1::80 \
        -e APP_RUNTIME_MODE=api \
        -e APP_ENV=production \
        -e APP_DEBUG=false \
        -e APP_CONFIG_CHECK=false \
        -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
        -e APP_URL=https://api.example.test \
        -e DB_CONNECTION=pgsql -e DB_HOST=db.invalid -e REDIS_HOST=redis.invalid \
        -e SESSION_DRIVER=array -e CACHE_STORE=array \
        -e LOG_CHANNEL=stderr \
        "$image" >/dev/null

    local port base
    port="$(docker port "$container" 80/tcp | head -1 | sed 's/.*://')"
    base="http://127.0.0.1:${port}"

    local ready=false
    for _ in $(seq 1 90); do
        if curl -fs -o /dev/null "${base}/up"; then ready=true; break; fi
        sleep 1
    done
    [ "$ready" = true ] || { docker logs "$container" >&2; die "the api runtime never answered /up."; }

    # --- Listeners -------------------------------------------------------------
    # Only the application port may be reachable from outside the container's
    # network namespace. php-fpm is a unix socket; its ping/status listener is on
    # loopback; supervisord's control socket is a unix socket.
    local listeners exposed
    listeners="$(docker exec "$container" ss -Hltun)"
    exposed="$(awk '{ print $1, $5 }' <<<"$listeners" \
        | grep -vE ' (127\.[0-9.]+|\[::1\]):[0-9]+$' \
        | grep -vE '^tcp (0\.0\.0\.0|\*|\[::\]):80$' || true)"
    if [ -n "$exposed" ]; then
        fail "unexpected non-loopback listeners:"$'\n'"$exposed"
    fi

    # --- Canaries --------------------------------------------------------------
    # Where secrets and private data sit in a RUNNING deployment. canary.php
    # proves nothing outside public/ can be EXECUTED, not merely downloaded.
    docker exec "$container" sh -c "
        set -e
        mkdir -p ${app}/.git ${app}/storage/app/private
        for f in .env .env.production .git/config storage/oauth-private.key \
                 storage/logs/laravel.log storage/app/canary.txt \
                 storage/app/private/canary.txt database/backup.sql public/.env; do
            echo '${token}' > ${app}/\$f
        done
        echo '<?php echo \"${token}\";' > ${app}/canary.php
        echo 'CONTROL_${token}' > ${app}/public/exposure-control.txt
    "

    # --- Probe set -------------------------------------------------------------
    # Every file shipped outside public/ (vendor is sampled: it is third-party
    # code under one directory, and its root is what an attacker requests), the
    # canaries, and traversal/normalisation variants aimed at the .env canary.
    # The private-disk canaries are also requested at /storage/<name>: the
    # `local` disk's root is storage/app, so that is where Laravel's signed
    # `storage/{path}` route would serve them if its signature check went away.
    docker exec "$container" sh -c "
        cd ${app}
        find . -path ./public -prune -o -path ./vendor -prune -o -type f -print
        ls vendor/autoload.php vendor/composer/installed.json
    " | sed 's|^\./||' | sort -u > "$work/files"

    # `-i`, or docker exec drops stdin, xargs hashes nothing, and the "response
    # is the file itself" assertion below compares against an empty list. The
    # count check makes an incomplete hash list fail instead of passing quietly.
    docker exec -i "$container" sh -c "cd ${app} && xargs sha256sum" < "$work/files" > "$work/hashes"
    [ "$(wc -l < "$work/hashes")" -eq "$(wc -l < "$work/files")" ] \
        || die "hashed $(wc -l < "$work/hashes" | tr -d ' ') of $(wc -l < "$work/files" | tr -d ' ') shipped files; the content check would be vacuous."

    {
        sed 's|^|/|' "$work/files"
        printf '%s\n' \
            /public/.env /.git/ /storage/ /storage/app/canary.txt /storage/app/private/canary.txt \
            /storage/canary.txt /storage/private/canary.txt \
            '/%2e%2e/.env' '/..%2f.env' '/%2e%2e%2f.env' '//.env' '/./.env' \
            '/index.php/.env' '/index.php/../.env' '/index.php/%2e%2e/.env' \
            '/css/../../.env' '/css/..%2f..%2f.env' '/robots.txt/.env' \
            '/canary.php/x' '/index.php/../canary.php' \
            '/fpm-status' '/fpm-status?full' '/fpm-ping'
    } > "$work/probes"

    echo "check-exposure: image: probing $(wc -l < "$work/probes" | tr -d ' ') paths against ${image}."

    # Output a web server must never produce for a non-public path.
    local leak_markers='<\?php|APP_KEY=|DB_PASSWORD=|BEGIN (RSA |EC )?PRIVATE KEY|\[core\]|Stack trace|/var/www/html|pool:[[:space:]]|^pong$'

    local probe url hops status location body_hash rel expected_hash
    while IFS= read -r probe; do
        url="${base}${probe}"
        hops=0

        # Follow redirects by hand: same-origin hops are followed and inspected,
        # an off-origin hop ends the chain (nothing of ours is served there).
        while :; do
            read -r status location < <(curl -s --path-as-is -g -o "$work/body" -D "$work/headers" \
                -w '%{http_code} %{redirect_url}\n' "$url" || echo "000 -")

            if grep -aqF "$token" "$work/body"; then
                fail "${probe}: response body contains a canary (HTTP ${status})"
            fi
            if grep -aqE "$leak_markers" "$work/body"; then
                fail "${probe}: response body contains source or debug output (HTTP ${status})"
            fi
            if grep -aqiE '^x-powered-by:|^server:.*[0-9]' "$work/headers"; then
                fail "${probe}: response headers disclose software versions"
            fi

            case "$status" in
                3??)
                    hops=$((hops + 1))
                    [ "$hops" -le 5 ] || { fail "${probe}: redirect loop"; break; }
                    case "$location" in
                        "${base}"/*) url="$location" ;;
                        *) break ;;
                    esac
                    ;;
                2??)
                    fail "${probe}: served with HTTP ${status} after ${hops} redirect(s)"
                    break
                    ;;
                *) break ;;
            esac
        done

        # The file's own bytes must not come back under any status.
        rel="${probe#/}"
        expected_hash="$(awk -v f="$rel" '$2 == f { print $1 }' "$work/hashes")"
        if [ -n "$expected_hash" ]; then
            body_hash="$(sha256_of "$work/body")"
            [ "$body_hash" != "$expected_hash" ] || fail "${probe}: response body is the file itself"
        fi
    done < "$work/probes"

    # --- Positive controls -----------------------------------------------------
    # Without these, a container that answered nothing would pass every check.
    curl -s "${base}/exposure-control.txt" | grep -qF "CONTROL_${token}" \
        || fail "control: a file in public/ was not served, so the probes above prove nothing"
    [ "$(curl -s -o /dev/null -w '%{http_code}' "${base}/up")" = 200 ] \
        || fail "control: the application did not answer /up through php-fpm"
    docker exec "$container" curl -fs "http://127.0.0.1:$(docker exec "$container" sh -c '. /run/app-runtime.env; echo $APP_FPM_PING_PORT')/fpm-ping" \
        | grep -qx pong \
        || fail "control: php-fpm ping did not answer on its loopback listener"

    if [ "$failures" -gt 0 ]; then
        die "${failures} exposure check(s) failed."
    fi

    echo "check-exposure: image: no file outside public/ is retrievable or executable at its mirrored path; only :80 is exposed."
}

case "${1:-}" in
    compose) check_compose "${2:-docker-compose.yml}" ;;
    image)
        [ -n "${2:-}" ] || die "usage: $0 image <tag>"
        check_image "$2"
        ;;
    *) die "usage: $0 compose [file] | image <tag>" ;;
esac
