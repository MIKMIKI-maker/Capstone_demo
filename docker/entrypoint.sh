#!/bin/bash
set -e

if [ ! -d /var/lib/mysql/mysql ]; then
    mysqld --initialize-insecure --user=mysql --datadir=/var/lib/mysql
fi

mysqld_safe --datadir=/var/lib/mysql >/var/log/mysql-init.log 2>&1 &

until mysqladmin ping --silent; do
    sleep 1
done

# The PHP app connects to 127.0.0.1 as root with no password.
# Ubuntu's default auth_socket plugin only permits local socket logins.
mysql -uroot <<'SQL'
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

if [ -f /var/www/html/Capstone_demo/DATABASE/database.sql ]; then
    mysql -uroot < /var/www/html/Capstone_demo/DATABASE/database.sql || true
fi

mysqladmin -uroot shutdown

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
