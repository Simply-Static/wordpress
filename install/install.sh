#! /usr/bin/bash

set -e

# Needed to get the WP-CLI commands to avoid asking for the TTY size, which
# doesn't work because we don't have the stty command it uses.
export COLUMNS=80

echo "Creating required directories..."

mkdir -p /app/wp-content/plugins
echo "" > /app/wp-content/plugins/.keep

mkdir -p /app/wp-content/mu-plugins
echo "" > /app/wp-content/mu-plugins/.keep

mkdir -p /app/wp-content/upgrade
echo "" > /app/wp-content/upgrade/.keep

echo "Installing WordPress core..."

php /app/wp-cli.phar \
  --allow-root \
  --path=/app \
  core install \
  --url="$WASMER_APP_URL"  \
  --title="$WP_SITE_TITLE" \
  --admin_user="$WP_ADMIN_USERNAME" \
  --admin_password="$WP_ADMIN_PASSWORD" \
  --admin_email="$WP_ADMIN_EMAIL" \
  --locale="$WP_LOCALE" || true

echo "Updating icon..."

php /app/wp-cli.phar --allow-root --path=/app \
  media import "https://i0.wp.com/learn.wordpress.org/files/2023/08/WordPress-logotype-simplified.png" --porcelain | \
  php /app/wp-cli.phar --allow-root --path=/app option update site_icon || true

echo "Set up constants..."
php /app/wp-cli.phar --allow-root --path=/app config set SSS_HAS_MIGRATION "$SSS_HAS_MIGRATION";
php /app/wp-cli.phar --allow-root --path=/app config set SSS_SECRET_KEY "$SSS_SECRET_KEY";
php /app/wp-cli.phar --allow-root --path=/app config set SSS_ACCESS_KEY "$SSS_ACCESS_KEY";
php /app/wp-cli.phar --allow-root --path=/app config set SSS_PULL_ZONE "$SSS_PULL_ZONE";
php /app/wp-cli.phar --allow-root --path=/app config set SSS_STORAGE_ZONE "$SSS_STORAGE_ZONE";
php /app/wp-cli.phar --allow-root --path=/app config set SSS_STORAGE_HOST "$SSS_STORAGE_HOST";
php /app/wp-cli.phar --allow-root --path=/app config set SSS_SITE_ID "$SSS_SITE_ID";
php /app/wp-cli.phar --allow-root --path=/app config set SSS_EMAIL "$SSS_EMAIL";
php /app/wp-cli.phar --allow-root --path=/app config set SSS_LICENSE "$SSS_LICENSE";
php /app/wp-cli.phar --allow-root --path=/app config set SSS_BASIC_AUTH_USER "$SSS_BASIC_AUTH_USER";
php /app/wp-cli.phar --allow-root --path=/app config set SSS_BASIC_AUTH_PASSWORD "$SSS_BASIC_AUTH_PASSWORD";

echo "Installing theme and plugins..."

php /app/wp-cli.phar \
  --allow-root \
  --path=/app \
  wasmer-aio-install install \
  --locale="$WP_LOCALE" \
   --theme=/app/install/twentytwentyfive.zip || true

echo "Installing Simply Static and Simply Static Pro"
php /app/wp-cli.phar --allow-root --path=/app plugin install simply-static --activate;
php /app/wp-cli.phar --allow-root --path=/app plugin install https://api.static.studio/storage/v1/object/public/plugins/simply-static-pro.zip --activate;

echo "Install and activate Ollie theme."
php /app/wp-cli.phar --allow-root --path=/app theme install ollie --activate;

echo "Installing MU plugin".
php /app/wp-cli.phar --allow-root --path=/app plugin install https://api.static.studio/storage/v1/object/public/plugins/simply-static-studio-helper.zip;
mv /app/wp-content/plugins/simply-static-studio-helper/ /app/wp-content/mu-plugins/
curl -L https://api.static.studio/storage/v1/object/public/plugins/load.php -o /app/wp-content/mu-plugins/load.php;

echo "Installation complete"
