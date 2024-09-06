FROM php:fpm-alpine

RUN apk --no-cache add linux-headers

RUN docker-php-ext-install pdo pdo_mysql bcmath sockets

WORKDIR /trackr