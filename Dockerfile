FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql && \
    a2enmod rewrite && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    echo "umask 0022" >> /etc/apache2/envvars

COPY docker-entrypoint.sh /usr/local/bin/radar-entrypoint.sh
RUN chmod +x /usr/local/bin/radar-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["radar-entrypoint.sh"]
