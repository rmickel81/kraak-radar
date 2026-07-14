FROM php:8.3-apache

# Instalar extensiones
RUN docker-php-ext-install pdo pdo_mysql && \
    a2enmod rewrite && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Puerto
EXPOSE 80
