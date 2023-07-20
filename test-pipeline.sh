#!/usr/bin/env bash
set -a

echo -e "\033[0m \033[1;35m Creating .env file \033[0m"
cp .env.testing .env

source .env
COMMANDS=""

echo -e "\033[0m \033[1;35m Starting services \033[0m"
docker compose up -d laravel-api laravel-db-test

# Wait for the containers to initialize
echo -e "\033[0m \033[1;35m Waiting for the containers to initialize \033[0m"

while ! docker exec laravel-db-test mysql -uroot -p$DB_ROOT_PASSWORD -e "SELECT 1" >/dev/null 2>&1; do
    sleep 1
done

COMMANDS="
    chmod -R 777 storage
    chmod -R 777 bootstrap/cache
    composer update
    php artisan migrate:fresh --seed
    php artisan passport:install
    php artisan passport:keys
    php artisan test --profile
"

docker exec laravel-api bash -c "$COMMANDS"
