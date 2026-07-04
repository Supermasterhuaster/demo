#!/bin/sh
set -e

cd /var/www/html

if [ ! -d vendor ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
    chown -R devuser:devuser vendor
fi

echo "Waiting for MySQL..."
until php artisan db:show 2>/dev/null; do
    echo "MySQL is unavailable - sleeping"
    sleep 2
done
echo "MySQL is up"

if [ -f .env ] && ! grep -qE '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
    chown devuser:devuser .env
fi

php artisan migrate --force

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R devuser:devuser storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

exec "$@"
