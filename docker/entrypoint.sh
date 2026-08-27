#!/bin/bash
set -e

# Fix MySQL directories and permissions
mkdir -p /var/run/mysqld /var/lib/mysql
chown -R mysql:mysql /var/run/mysqld /var/lib/mysql
chmod 777 /var/run/mysqld

# Initialize MySQL data directory if empty
if [ ! -d "/var/lib/mysql/mysql" ]; then
    mysqld --initialize-insecure --user=mysql
fi

# Start MySQL service
service mysql start

# Wait for MySQL to fully accept connections
until mysqladmin ping --silent; do
    echo "Waiting for MySQL server..."
    sleep 1
done

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

# Set proper ownership for Web Server
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Start Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf