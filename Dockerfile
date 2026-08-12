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
# WHAT IS CACHED HERE, AND WHAT IS NOT:
#
# `view:cache` compiles Blade templates. Its output depends only on files in the
# build context, so freezing it into a layer is correct and saves the work at
# every container start.
#
# `config:cache` and `route:cache` are deliberately NOT run here. config:cache
# evaluates every env() call and writes the RESULT; Laravel then loads that file
# and stops reading .env and config/*.php altogether. Because .dockerignore
# excludes .env, a build-time cache freezes the DEFAULTS from config/*.php — so
# the image would ship DB_HOST=127.0.0.1 baked in and silently ignore whatever
# the platform injects at runtime. route:cache has the same problem via
# `app()->environment()` and config() calls in the route files.
#
# Both are run by docker/entrypoint.sh at container start instead, against the
# environment the container actually received. See tests/Feature/ConfigurationContractFeatureTest.php and
# the `docker` job in .github/workflows/test.yml, which assert exactly this.
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
    composer install --no-dev --optimize-autoloader --no-interaction && \
    php artisan view:cache && \
    chown -R www-data:www-data storage bootstrap/cache

# The runtime selector. Without it the base image's single entrypoint starts
# nginx, php-fpm AND `php artisan horizon` in every container, so each API
# replica would also add a Horizon master. See docker/entrypoint.sh.
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint.sh
COPY docker/healthcheck.sh /usr/local/bin/app-healthcheck.sh
RUN chmod 0755 /usr/local/bin/app-entrypoint.sh /usr/local/bin/app-healthcheck.sh

# The default is the web runtime, so a platform that overrides nothing gets an API
# container and never an accidental queue worker. Every other mode is opt-in via
# this variable or a command override.
ENV APP_RUNTIME_MODE=api

# Overrides the base image's healthcheck, which curls php-fpm unconditionally and
# would therefore mark a healthy worker container unhealthy.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD /usr/local/bin/app-healthcheck.sh

ENTRYPOINT ["/usr/local/bin/app-entrypoint.sh"]
