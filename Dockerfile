FROM php:8.1.9-apache

RUN apt-get update && apt-get install -y libzip-dev zip

COPY trackr-virtualhost.conf /etc/apache2/sites-available/000-default.conf

RUN docker-php-ext-install pdo pdo_mysql zip bcmath sockets

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html

RUN a2enmod rewrite
RUN service apache2 restart