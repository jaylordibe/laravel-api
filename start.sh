#!/usr/bin/env bash
set -e

./vendor/bin/sail up -d
./vendor/bin/sail composer update
./vendor/bin/sail artisan migrate
