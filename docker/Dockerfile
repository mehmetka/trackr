FROM php:7.4-apache

COPY apache.conf /etc/apache2/sites-available/000-default.conf

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html

RUN a2enmod rewrite
RUN service apache2 restart