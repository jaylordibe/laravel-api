#!/usr/bin/env bash
set -e

./vendor/bin/sail up -d
./vendor/bin/sail composer install
./vendor/bin/sail artisan migrate
./vendor/bin/sail exec -t laravel.test ./vendor/bin/paratest
./vendor/bin/sail down
