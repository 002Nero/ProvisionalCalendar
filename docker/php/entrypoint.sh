#!/bin/bash
set -e

# --- Step 1: Fix permissions ---
echo "Fixing permissions..."
chown -R www-data:www-data /var/www/html
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# --- Step 2: Git safe directory (avoids 'dubious ownership' issue) ---
git config --global --add safe.directory /var/www/html || true

# --- Step 3: Composer setup ---
mkdir -p /var/www/.composer/cache
chown -R www-data:www-data /var/www/.composer
chmod -R 775 /var/www/.composer

# If the Composer autoload file is missing, install dependencies
if [ ! -f "/var/www/html/vendor/autoload.php" ]; then
  echo "Installing Composer dependencies..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
  # Ensure vendor files are readable by www-data
  chown -R www-data:www-data /var/www/html/vendor || true
fi

# --- Step 4: Environment setup ---
if [ ! -f "/var/www/html/.env" ] && [ -f "/var/www/html/.env.example" ]; then
  echo "Creating .env file..."
  cp /var/www/html/.env.example /var/www/html/.env
fi

# --- Step 4.5: Ensure APP_KEY exists (idempotent) ---
# If APP_KEY is missing or empty in .env, generate it via artisan
if [ -f "/var/www/html/.env" ]; then
  APP_KEY_VAL=$(grep '^APP_KEY=' /var/www/html/.env | cut -d'=' -f2- || true)
  if [ -z "${APP_KEY_VAL}" ]; then
    echo "Generating application key (APP_KEY) ..."
    # php artisan key:generate will update the .env file
    php artisan key:generate --force || echo "Warning: APP_KEY generation failed"
  else
    echo "APP_KEY already set, skipping key generation"
  fi
fi

# --- Step 5: Wait for the database ---
#echo "Waiting for database to be ready..."
#until php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); } catch (Exception \$e) { exit(1); }"; do
#  echo "Database is not ready yet. Waiting..."
#  sleep 2
#done
#echo "Database is ready!"

# --- Step 6: Run migrations safely ---
echo "Running Laravel migrations..."
php artisan migrate || echo "Migrations skipped or failed gracefully."
php artisan db:seed || echo "Seeding skipped or failed gracefully."

# --- Step 7: Start PHP-FPM ---
echo "Starting PHP-FPM..."
exec php-fpm -F
