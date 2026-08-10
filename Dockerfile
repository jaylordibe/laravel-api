FROM jaylordibe/laravel-php:8.5

WORKDIR /var/www/html

# Copy application code
COPY . .

# Install dependencies (production only)
#
# The runtime-writable directories are recreated first. .dockerignore excludes
# storage/logs, storage/framework/cache and storage/framework/views on purpose —
# they hold a developer's local log/cache output, which must never ship inside an
# image — but excluding them also removes the DIRECTORIES, and `view:cache` runs
# `view:clear` first, which aborts with "View path not found" when the view path
# is missing. php-fpm then needs the same paths writable at runtime.
#
# The chown runs LAST, after the artisan cache commands have written into
# bootstrap/cache as root; doing it earlier would leave those files root-owned.
# nginx and php-fpm serve as www-data in the base image (nginx.conf: `user
# www-data;`), so anything the application writes must belong to that user.
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache && \
    composer install --no-dev --optimize-autoloader && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    chown -R www-data:www-data storage bootstrap/cache
