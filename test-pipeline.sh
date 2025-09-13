#!/usr/bin/env bash
set -e
set -a

echo -e "\033[0m \033[1;35m Creating .env file \033[0m"
cp .env.example .env
source .env

echo -e "\033[0m \033[1;35m Starting services... \033[0m"
docker compose up -d ${SERVICE_NAME}-api ${SERVICE_NAME}-db-test

echo -e "\033[0m \033[1;35m Waiting for the containers to initialize \033[0m"
while ! docker exec ${SERVICE_NAME}-db-test mysql -u$TEST_DB_USERNAME -p$TEST_DB_PASSWORD -e "SELECT 1" >/dev/null 2>&1; do
    sleep 1
done

commands="
    chmod -R 777 storage
    chmod -R 777 bootstrap/cache
    composer install --prefer-dist --no-progress --no-interaction
    php artisan key:generate
    php artisan migrate:fresh --seed --env=testing
    php artisan passport:keys --force
    echo -e '\n' | php artisan passport:client --personal --env=testing
    php artisan test --parallel
"

docker exec ${SERVICE_NAME}-api bash -c "$commands"
