#!/usr/bin/env bash
set -a

echo -e "\033[0m \033[1;35m Stopping existing services \033[0m"
docker compose down

TYPE=$1

if [ "$TYPE" = "fresh" ]; then
    if [[ -f ".env" ]]; then
        echo -e "\033[0m \033[1;35m Removing existing .env file \033[0m"
        rm .env
    fi

    if [[ -d "docker/database/data" ]]; then
        echo -e "\033[0m \033[1;35m Removing existing database data \033[0m"
        sudo rm -r docker/database/data
    fi

    if [[ -d "docker/database/test-data" ]]; then
        echo -e "\033[0m \033[1;35m Removing existing test database data \033[0m"
        sudo rm -r docker/database/test-data
    fi
fi

if [[ ! -f ".env" ]]; then
    echo -e "\033[0m \033[1;35m Creating .env file \033[0m"
    cp .env.example .env
fi

source .env
COMMANDS=""

echo -e "\033[0m \033[1;35m Starting services \033[0m"
docker compose up -d

# Wait for the containers to initialize
echo -e "\033[0m \033[1;35m Waiting for the containers to initialize \033[0m"

while ! docker exec laravel-db mysql -uroot -p$DB_ROOT_PASSWORD -e "SELECT 1" >/dev/null 2>&1; do
    sleep 1
done

if [ "$TYPE" = "fresh" ]; then
    COMMANDS="
    chmod -R 777 storage
    chmod -R 777 bootstrap/cache
    composer update
    php artisan migrate:fresh --seed
    php artisan key:generate
    php artisan passport:install
    php artisan passport:keys
    "
else
    COMMANDS="
    chmod -R 777 storage
    chmod -R 777 bootstrap/cache
    composer update
    php artisan migrate
    "
fi

docker exec -it laravel-api bash -c "$COMMANDS"

echo -e "\033[0m \033[1;35m Application is running at: \033[0m"
echo -e "\033[0m \033[1;32m \t http://localhost:9000/ \033[0m"
echo -e "\033[0m \033[1;35m phpMyAdmin is running at: \033[0m"
echo -e "\033[0m \033[1;32m \t http://localhost:9001/ \033[0m"
echo -e "\033[0m \033[1;35m Test environment phpMyAdmin is running at: \033[0m"
echo -e "\033[0m \033[1;32m \t http://localhost:9002/ \033[0m"
