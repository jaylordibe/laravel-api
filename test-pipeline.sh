#!/usr/bin/env bash
set -a

echo -e "\033[0m \033[1;35m Creating .env file \033[0m"
cp .env.example .env
source .env

echo -e "\033[0m \033[1;35m Starting services \033[0m"
docker compose up -d laravel-api laravel-db-test

echo -e "\033[0m \033[1;35m Waiting for the containers to initialize \033[0m"
while ! docker exec laravel-db-test mysql -uroot -p$TEST_DB_ROOT_PASSWORD -e "SELECT 1" >/dev/null 2>&1; do
    sleep 1
done

COMMANDS="
    chmod -R 777 storage
    chmod -R 777 bootstrap/cache
    composer install --prefer-dist --no-progress --no-interaction
    php artisan migrate:fresh --seed --env=testing
    php artisan passport:install --env=testing
    php artisan passport:keys --env=testing
    php artisan test --parallel
"

docker exec laravel-api bash -c "$COMMANDS"
