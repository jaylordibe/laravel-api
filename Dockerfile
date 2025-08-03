FROM jaylordibe/laravel-php:8.4

WORKDIR /var/www/html

# Copy application code
COPY . .

# Install dependencies (production only)
RUN composer install --no-dev --optimize-autoloader && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache
