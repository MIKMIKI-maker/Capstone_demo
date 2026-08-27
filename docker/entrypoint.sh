#!/bin/bash
set -e

# Start MySQL service
service mysql start

# Import SQL files into spedalm_db
if [ -d /var/www/html/DATABASE ]; then
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS spedalm_db;" || true
    for f in /var/www/html/DATABASE/*.sql; do
        if [ -f "$f" ]; then
            echo "Importing $f into spedalm_db..."
            mysql -u root spedalm_db < "$f" || true
        fi
    done
fi

# Ensure correct permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Start Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf