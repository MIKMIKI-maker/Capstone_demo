FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    apache2 \
    php \
    libapache2-mod-php \
    php-mbstring \
    php-mysqli \
    mysql-server \
    supervisor \
    && a2enmod rewrite \
    && rm -rf /var/www/html/* \
    && mkdir -p /var/www/html/Capstone_demo /var/run/mysqld \
    && chown -R mysql:mysql /var/run/mysqld /var/lib/mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html/Capstone_demo
EXPOSE 80 3306
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
