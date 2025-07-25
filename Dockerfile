FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql
RUN a2enmod rewrite

COPY ./docker/apache.conf /etc/apache2/sites-available/000-default.conf