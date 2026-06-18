FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql

# Copiamos ambas carpetas al servidor
COPY ./admin /var/www/html/admin
COPY ./institucion /var/www/html/institucion

EXPOSE 80