#!/usr/bin/env bash
set -e

type=$1

if [ "$type" = "fresh" ]; then
    docker compose down -v
    ./vendor/bin/sail build --no-cache
fi

./vendor/bin/sail up -d
./vendor/bin/sail composer update
./vendor/bin/sail artisan migrate
