#!/usr/bin/env bash
set -e

./vendor/bin/sail artisan migrate:fresh --seed --env=testing
echo -e "\n" | ./vendor/bin/sail artisan passport:client --personal --env=testing
./vendor/bin/sail exec -T laravel.test ./vendor/bin/paratest
