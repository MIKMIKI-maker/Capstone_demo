#!/bin/bash
set -e

mkdir -p /var/run/mysqld /var/lib/mysql
chown -R mysql:mysql /var/run/mysqld /var/lib/mysql
chmod 777 /var/run/mysqld

if [ ! -d "/var/lib/mysql/mysql" ]; then
    mysqld --initialize-insecure --user=mysql
fi

service mysql start

until mysqladmin ping --silent; do
    sleep 1
done

# Grant all privileges to root for localhost & 127.0.0.1
mysql -u root -e "CREATE USER IF NOT EXISTS 'root'@'localhost' IDENTIFIED BY '';" || true
mysql -u root -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;" || true
mysql -u root -e "CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '';" || true
mysql -u root -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;" || true
mysql -u root -e "FLUSH PRIVILEGES;" || true

if [ -d /var/www/html/DATABASE ]; then
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS spedalm_db;" || true
    for f in /var/www/html/DATABASE/*.sql; do
        if [ -f "$f" ]; then
            mysql -u root spedalm_db < "$f" || true
        fi
    done
fi

chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf