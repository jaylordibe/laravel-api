#!/usr/bin/env bash

echo -e "\033[0m \033[1;35m Running tests \033[0m"

CLASS_NAME_OR_METHOD_NAME="$1"
FILE_PATH="$2"
FILTER=""

if [[ -n "$CLASS_NAME_OR_METHOD_NAME" && -n "$FILE_PATH" ]]; then
    FILTER="--filter $CLASS_NAME_OR_METHOD_NAME $FILE_PATH"
fi

#COMMANDS="
#php artisan migrate:fresh --seed --env=testing
#php artisan passport:install --env=testing
#php artisan passport:keys --env=testing
#php artisan test --profile $FILTER
#"

COMMANDS="
php artisan migrate --env=testing
php artisan test --profile $FILTER
"

docker exec -it laravel-api bash -c "$COMMANDS"
