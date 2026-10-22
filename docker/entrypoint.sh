#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Compose me-mount .env dari host. Kalau belum ada, Docker membuat DIREKTORI
# bernama .env — bukan file — sehingga `cp` di bawah gagal dan container
# crash-loop. Buang direktori itu dulu, lalu salin dari example.
if [ -d .env ]; then
    rm -rf .env
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

# public/storage di-ignore git, jadi link-nya harus dibuat ulang tiap container
# baru. Perintah artisan di bawah mem-boot aplikasi, dan boot pertama menulis
# storage/framework/app-key — karena itu chown dilakukan SETELAHNYA, bukan
# sebelumnya, supaya file yang baru dibuat tidak tertinggal milik root.
php artisan storage:link --force >/dev/null 2>&1 || true

# Wizard menulis .env saat runtime; config ter-cache dari start sebelumnya akan
# membuat perubahan itu tidak terbaca.
php artisan config:clear >/dev/null 2>&1 || true

chown -R www-data:www-data storage bootstrap/cache
chown www-data:www-data .env 2>/dev/null || true

# `docker compose exec app php artisan ...` jalan sebagai root dan membuat
# storage/logs/laravel.log milik root 644. Apache (www-data) lalu gagal append
# dan error aslinya tertutup pesan "could not be opened in append mode".
# Setgid saja tidak menolong: file baru tetap 644 karena exec tidak mewarisi
# umask entrypoint, dan setfacl tidak ada di image ini. Jadi file log dibuat
# di sini dengan mode 664, dan LOG_CHANNEL=stderr di .env.docker membuat proses
# root tidak lagi menulis ke file itu sama sekali.
touch storage/logs/laravel.log
chown www-data:www-data storage/logs/laravel.log
chmod 664 storage/logs/laravel.log

exec apache2-foreground
