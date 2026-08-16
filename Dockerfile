FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-progress

COPY . /app
RUN composer dump-autoload --no-interaction \
    && chown -R www-data:www-data /app/storage \
    && sed -ri 's!/var/www/html!/app/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf 'ServerName localhost\nLimitRequestFieldSize 65536\nLimitRequestLine 16384\n<Directory /app/public>\n    Options Indexes FollowSymLinks\n    AllowOverride None\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/app.conf \
    && a2enconf app

EXPOSE 80
