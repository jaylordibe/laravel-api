#!/usr/bin/env bash
set -a

echo -e "\033[0m \033[1;35m Starting services \033[0m"
docker compose up -d laravel-api laravel-db-test

COMMANDS="
    chmod -R 777 storage
    chmod -R 777 bootstrap/cache
    composer update
    php artisan migrate:fresh --seed --env=testing
    php artisan passport:install --env=testing
    php artisan passport:keys --env=testing
    php artisan test --profile
"

docker exec laravel-api bash -c "$COMMANDS"
