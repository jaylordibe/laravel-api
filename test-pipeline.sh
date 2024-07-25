#!/usr/bin/env bash
set -e

cp .env.example .env
#source .env

#docker compose down -v
#docker compose up -d laravel.test
#docker compose exec laravel.test composer install
#docker compose down
composer install

./vendor/bin/sail up -d
./vendor/bin/sail composer install
./vendor/bin/sail artisan migrate:fresh --seed --env=testing
echo -e "\n" | ./vendor/bin/sail artisan passport:client --personal --env=testing
./vendor/bin/sail exec -T laravel.test ./vendor/bin/paratest
./vendor/bin/sail down
