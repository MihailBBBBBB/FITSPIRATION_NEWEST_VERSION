FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN a2enmod rewrite proxy proxy_http proxy_wstunnel headers

WORKDIR /var/www/html
COPY . /var/www/html/

RUN cp /var/www/html/docker/apache-websocket.conf /etc/apache2/conf-available/apache-websocket.conf \
	&& a2enconf apache-websocket

RUN chmod +x /var/www/html/docker/start-container.sh

RUN mkdir -p /var/www/html/images
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 775 /var/www/html/images

EXPOSE 80

CMD ["/var/www/html/docker/start-container.sh"]