#!/bin/bash
set -e

# Fix MySQL directory setup
mkdir -p /var/run/mysqld /var/lib/mysql
chown -R mysql:mysql /var/run/mysqld /var/lib/mysql
chmod 777 /var/run/mysqld

# Initialize MySQL data if empty
if [ ! -d "/var/lib/mysql/mysql" ]; then
    mysqld --initialize-insecure --user=mysql
fi

# Start MySQL
service mysql start

# Wait for MySQL readiness
until mysqladmin ping --silent; do
    sleep 1
done

# Import database
if [ -d /var/www/html/DATABASE ]; then
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS spedalm_db;" || true
    for f in /var/www/html/DATABASE/*.sql; do
        if [ -f "$f" ]; then
            mysql -u root spedalm_db < "$f" || true
        fi
    done
fi

# Fix Web permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Start Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf