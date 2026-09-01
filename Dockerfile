# Minimal image for a TESTING/QA deploy (Render free tier), not tuned
# for production. Uses PHP's built-in server (`artisan serve`) rather
# than php-fpm+nginx — no extra process manager to configure, which is
# exactly the trade-off worth making for a throwaway QA environment.
# Revisit before a real production deploy (see docs/PILOT_DEPLOYMENT_CHECKLIST.md).
FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libonig-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring zip bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && php artisan storage:link || true

# Render sets $PORT at runtime; migrate on boot so a fresh Postgres
# database is ready before the server starts accepting requests.
CMD sh -c "php artisan migrate --force && php artisan serve --host 0.0.0.0 --port ${PORT:-8000}"
