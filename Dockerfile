FROM php:7.3-apache

COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY php/php.ini /usr/local/etc/php/php.ini
COPY . /var/www/
