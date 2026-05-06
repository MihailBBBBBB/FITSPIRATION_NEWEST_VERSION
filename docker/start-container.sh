#!/bin/sh
set -eu

php /var/www/html/websocket/messages-server.php >> /proc/self/fd/1 2>> /proc/self/fd/2 &

exec apache2-foreground