FROM php:7.3.29-apache

COPY trackr-virtualhost.conf /etc/apache2/sites-available/000-default.conf

RUN docker-php-ext-install pdo pdo_mysql

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html

RUN a2enmod rewrite
RUN service apache2 restart