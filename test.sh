#!/usr/bin/env bash

echo -e "\033[0m \033[1;35m Running tests \033[0m"
docker exec -it laravel-api bash -c "php artisan migrate --env=testing && php artisan test"
