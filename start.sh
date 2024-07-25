#!/usr/bin/env bash
set -e

type=$1

if [ "$type" = "fresh" ]; then
    if [[ -d "vendor" ]]; then
        echo -e "\033[0m \033[1;35m Removing existing database data \033[0m"
        rm -r vendor
    fi

    docker run --rm \
        --pull=always \
        -v "$(pwd)":/opt \
        -w /opt \
        laravelsail/php83-composer:latest \
        bash -c "composer install"

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
