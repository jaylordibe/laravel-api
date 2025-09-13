#!/usr/bin/env bash
set -e
set -a

echo -e "\033[0m \033[1;35m Running tests... \033[0m"

class_name_or_method_name="$1"
file_path="$2"
commands=""

if [[ -n "$class_name_or_method_name" && -n "$file_path" ]]; then
    commands="php artisan test --parallel --functional --filter=$class_name_or_method_name $file_path"
else
    commands="
    php artisan migrate:fresh --seed --env=testing
    echo -e '\n' | php artisan passport:client --personal --env=testing
    php artisan config:clear --env=testing
    php artisan cache:clear --env=testing
    php artisan route:clear --env=testing
    php artisan test --parallel
    "
fi

docker exec -it $SERVICE_NAME-api bash -c "$commands"
