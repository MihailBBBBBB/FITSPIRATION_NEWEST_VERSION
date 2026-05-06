FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html/

RUN mkdir -p /var/www/html/images
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 775 /var/www/html/images

EXPOSE 80