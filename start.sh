#!/usr/bin/env bash
set -e

type=$1

if [ "$type" = "fresh" ]; then
    docker compose down -v
#    ./vendor/bin/sail build --no-cache
    ./vendor/bin/sail up -d
    ./vendor/bin/sail composer update
    ./vendor/bin/sail artisan migrate:fresh --seed

    if [[ ! -f "storage/oauth-private.key" && ! -f "storage/oauth-public.key" ]]; then
        ./vendor/bin/sail artisan passport:keys
    fi

    echo -e "\n" | ./vendor/bin/sail artisan passport:client --personal
else
    ./vendor/bin/sail down
    ./vendor/bin/sail up -d
    ./vendor/bin/sail composer update
    ./vendor/bin/sail artisan migrate
fi
