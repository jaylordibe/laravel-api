#!/usr/bin/env bash
set -a

if [[ ! -f ".env" ]]; then
    echo -e "\033[0m \033[1;35m Creating .env file... \033[0m"
    cp .env.example .env
fi

source .env
TYPE=$1
COMMANDS=""

echo -e "\033[0m \033[1;35m Starting services... \033[0m"
docker-compose up -d

# Wait for the containers to initialize
echo -e "\033[0m \033[1;35m Waiting for the containers to initialize... \033[0m"

while ! docker exec laravel-boilerplate-database mysql -uroot -p$DB_ROOT_PASSWORD -e "SELECT 1" >/dev/null 2>&1; do
    sleep 1
done

if [ "$TYPE" = "fresh" ]; then
    COMMANDS="
    composer update
    php artisan migrate:fresh --seed
    php artisan key:generate
    php artisan passport:install
    php artisan passport:keys
    php artisan migrate:fresh --seed --env=testing
    php artisan key:generate --env=testing
    php artisan passport:install --env=testing
    php artisan passport:keys --env=testing
    "
else
    COMMANDS="
    composer update
    composer dump-autoload
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    php artisan migrate
    "
fi

docker exec -it laravel-boilerplate-api bash -c "$COMMANDS"

echo -e "\033[0m \033[1;35m Application is running at: \033[0m"
echo -e "\033[0m \033[1;32m \t http://localhost:8000/ \033[0m"
echo -e "\033[0m \033[1;35m phpMyAdmin is running at: \033[0m"
echo -e "\033[0m \033[1;32m \t http://localhost:8001/ \033[0m"
echo -e "\033[0m \033[1;35m Test environment phpMyAdmin is running at: \033[0m"
echo -e "\033[0m \033[1;32m \t http://localhost:8002/ \033[0m"
