# Smart Log Center - PHP 8.4 + Apache
#
# Aplikasi ini tidak punya build step frontend: CSS/JS sudah ada di
# public/assets dan tidak ada @vite di view mana pun. Karena itu tidak ada
# stage node/npm di sini, cukup PHP + Composer.
#
# Image ini sengaja single-stage. composer install butuh ekstensi (dom, gd,
# pdo_pgsql) yang dipakai dompdf dan PostgreSQL, jadi ekstensi harus terpasang
# sebelum Composer jalan.

FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libpng-dev \
        libzip-dev \
        libcurl4-openssl-dev \
        unzip \
    && docker-php-ext-install pdo pdo_pgsql gd zip \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Manifest dulu supaya layer vendor hanya dibangun ulang saat dependency berubah.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist

COPY . .

# --no-scripts WAJIB: tanpa itu hook post-autoload-dump menjalankan
# `artisan package:discover`, yang mem-boot AppServiceProvider -> AppKey::ensure()
# dan menulis storage/framework/app-key ke dalam image. Akibatnya semua container
# dari image ini berbagi APP_KEY yang sama. Laravel membuat ulang manifest
# package saat boot pertama, jadi manifest tidak perlu ada di image.
RUN composer dump-autoload --optimize --no-dev --no-scripts

# Document root harus public/ dan AllowOverride All (public/.htaccess dipakai).
# Vhost ditulis utuh, bukan lewat sed/conf-available: conf-enabled dimuat sebelum
# sites-enabled, jadi AllowOverride dari conf terpisah akan ditimpa balik ke None.
RUN printf '%s\n' \
        '<VirtualHost *:80>' \
        '    ServerAdmin webmaster@localhost' \
        '    DocumentRoot /var/www/html/public' \
        '    ErrorLog ${APACHE_LOG_DIR}/error.log' \
        '    CustomLog ${APACHE_LOG_DIR}/access.log combined' \
        '    <Directory /var/www/html/public>' \
        '        Options FollowSymLinks' \
        '        AllowOverride All' \
        '        Require all granted' \
        '    </Directory>' \
        '</VirtualHost>' \
        > /etc/apache2/sites-available/000-default.conf

RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint"]
