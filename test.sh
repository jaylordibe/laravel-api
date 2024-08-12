#!/usr/bin/env bash
set -e

echo -e "\033[0m \033[1;35m Running tests \033[0m"

CLASS_NAME_OR_METHOD_NAME="$1"
FILE_PATH="$2"
FILTER=""

if [[ -n "$CLASS_NAME_OR_METHOD_NAME" && -n "$FILE_PATH" ]]; then
    FILTER="--functional --filter=$CLASS_NAME_OR_METHOD_NAME $FILE_PATH"
fi

COMMANDS="
php artisan migrate:fresh --seed --env=testing
echo -e '\n' | php artisan passport:client --personal --env=testing
php artisan config:clear --env=testing
php artisan cache:clear --env=testing
php artisan route:clear --env=testing
php artisan test --parallel $FILTER
"

docker exec -it laravel-api bash -c "$COMMANDS"
