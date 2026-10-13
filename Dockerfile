FROM jaylordibe/laravel-php:8.5

WORKDIR /var/www/html

# Dependencies first, in their own layer, keyed on the lock file alone. A change to
# application code then reuses this layer instead of reinstalling every package.
# --no-scripts and --no-autoloader because neither the application nor artisan is
# here yet; both run below, once the code is.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

# Copy application code
COPY . .

# Production opcache settings, for the FPM SAPI only. See the file for why.
# PHP_VERSION comes from the base image's ENV, so this path follows its version.
# Named `zz-` because PHP loads conf.d in byte order and the base image installs an
# unprefixed `custom-php.ini`: a `99-` file sorts BEFORE it and would lose to any
# opcache setting the base adds there later.
COPY docker/php/opcache-production.ini /etc/php/${PHP_VERSION}/fpm/conf.d/zz-opcache-production.ini

# Finish the install (production only)
#
# The runtime-writable directories are recreated first. .dockerignore excludes
# storage/logs and storage/framework on purpose —
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
#
# storage/app is recreated for the same reason: .dockerignore excludes it so a
# developer's local uploads — and the Passport key files that sit beside them in
# storage/ — never enter a distributable layer, but the `local` and `public`
# disks still resolve their roots there at runtime.
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache && \
    composer dump-autoload --no-dev --optimize --no-interaction && \
    php artisan view:cache && \
    chown -R www-data:www-data storage bootstrap/cache

# The runtime selector. It prepares the application (runtime config cache, ports,
# startup checks) and hands the base entrypoint the command for the selected
# runtime — the web stack with Horizon switched off, or Horizon alone. Without it
# the base image's default command runs Horizon inside every web container, so
# each API replica would also add a Horizon master. See docker/entrypoint.sh.
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint.sh
COPY docker/healthcheck.sh /usr/local/bin/app-healthcheck.sh
RUN chmod 0755 /usr/local/bin/app-entrypoint.sh /usr/local/bin/app-healthcheck.sh

# The default is the web runtime, so a platform that overrides nothing gets an API
# container and never an accidental queue worker. Every other mode is opt-in via
# this variable or a command override.
ENV APP_RUNTIME_MODE=api

# Overrides the base image's healthcheck, which knows neither the php-fpm ping port
# this entrypoint may move (PORT=8080) nor what a healthy worker means for this
# application.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD /usr/local/bin/app-healthcheck.sh

# No CMD: the runtime is chosen by APP_RUNTIME_MODE, and setting ENTRYPOINT here
# resets the base image's CMD anyway. Arguments are read only by `artisan` mode.
ENTRYPOINT ["/usr/local/bin/app-entrypoint.sh"]
