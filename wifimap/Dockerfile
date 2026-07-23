FROM php:8.3-apache
COPY . /var/www/html/
RUN docker-php-ext-install mysqli \
    && mkdir -p /var/www/html/tools/php/uploads \
    && chown -R www-data:www-data /var/www/html/tools/php/uploads \
    && chmod -R 755 /var/www/html/tools/php/uploads
HEALTHCHECK CMD curl -f http://localhost/ || exit 1
