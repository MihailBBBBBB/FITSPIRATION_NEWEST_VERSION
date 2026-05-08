#!/bin/sh
set -eu

export FITSPIRATION_INTERNAL_WS_PORT="${FITSPIRATION_INTERNAL_WS_PORT:-8081}"

mkdir -p /var/www/html/images
chown -R www-data:www-data /var/www/html/images || true
chmod -R 0777 /var/www/html/images || true

php /var/www/html/websocket/messages-server.php >> /proc/self/fd/1 2>> /proc/self/fd/2 &

exec apache2-foreground