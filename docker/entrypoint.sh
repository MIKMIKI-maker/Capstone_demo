#!/bin/bash
set -e

# Start MySQL service
service mysql start

# Import database.sql if present and database is empty
if [ -f /var/www/html/DATABASE/database.sql ]; then
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS capstone_db;" || true
    mysql -u root capstone_db < /var/www/html/DATABASE/database.sql || true
fi

# Ensure correct permissions for Apache root
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Start Supervisor (Apache & MySQL background runner)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf