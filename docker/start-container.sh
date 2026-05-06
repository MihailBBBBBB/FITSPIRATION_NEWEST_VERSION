#!/bin/sh
set -eu

export FITSPIRATION_INTERNAL_WS_PORT="${FITSPIRATION_INTERNAL_WS_PORT:-8081}"

php /var/www/html/websocket/messages-server.php >> /proc/self/fd/1 2>> /proc/self/fd/2 &

exec apache2-foreground