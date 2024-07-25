#!/usr/bin/env bash
set -e

cp .env.example .env
docker compose down -v
docker compose up -d laravel.test
docker compose exec laravel.test composer install --prefer-dist --no-progress --no-suggest
docker compose down

./vendor/bin/sail up -d
./vendor/bin/sail composer install --prefer-dist --no-progress --no-suggest
./vendor/bin/sail artisan migrate:fresh --seed --env=testing
echo -e "\n" | ./vendor/bin/sail artisan passport:client --personal --env=testing
./vendor/bin/sail exec -T laravel.test ./vendor/bin/paratest
./vendor/bin/sail down
