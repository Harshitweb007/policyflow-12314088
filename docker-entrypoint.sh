#!/bin/bash
set -e

PORT=${PORT:-8080}

# Update Apache port to listen to Render's dynamic $PORT
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf 2>/dev/null || true
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf 2>/dev/null || true

# Ensure database directory and SQLite file exist with write permissions
mkdir -p /var/www/html/database
touch /var/www/html/database/database.sqlite
chmod -R 777 /var/www/html/database 2>/dev/null || true

# Ensure all framework storage folders exist
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Ensure APP_KEY exists so the application never throws MissingAppKeyException
if [ -z "$APP_KEY" ]; then
    echo "Notice: APP_KEY not provided in environment. Generating dynamic application key..."
    export APP_KEY=$(php artisan key:generate --show 2>/dev/null || echo "base64:ti6FLt7NqAMGgd5jhYC+FrsdPymok+vCE+mEOOHApfA=")
fi

# Ensure .env exists inside container so Apache PHP processes inherit critical variables
touch /var/www/html/.env
grep -q "APP_KEY=" /var/www/html/.env || echo "APP_KEY=${APP_KEY}" >> /var/www/html/.env
[ -n "$DATABASE_URL" ] && (grep -q "DATABASE_URL=" /var/www/html/.env || echo "DATABASE_URL=${DATABASE_URL}" >> /var/www/html/.env)
[ -n "$DB_CONNECTION" ] && (grep -q "DB_CONNECTION=" /var/www/html/.env || echo "DB_CONNECTION=${DB_CONNECTION}" >> /var/www/html/.env)

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force || true

# Clear cached config so dynamic runtime env variables are always read fresh
php artisan config:clear 2>/dev/null || true
php artisan route:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true

echo "Starting Apache on port ${PORT}..."
exec apache2-foreground
