FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    apache2 \
    php \
    libapache2-mod-php \
    php-mbstring \
    php-mysqli \
    php-curl \
    mysql-server \
    supervisor \
    && a2enmod rewrite \
    && rm -rf /var/www/html/* \
    && mkdir -p /var/run/mysqld \
    && chown -R mysql:mysql /var/run/mysqld /var/lib/mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Kinokopya ang LAHAT ng project files diretso sa root ng Apache server
COPY . /var/www/html/

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/uploads-security.conf /etc/apache2/conf-enabled/uploads-security.conf
COPY docker/uploads.ini /etc/php/8.1/apache2/conf.d/zz-uploads.ini
COPY docker/uploads.ini /etc/php/8.1/cli/conf.d/zz-uploads.ini
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
EXPOSE 80 3306
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]