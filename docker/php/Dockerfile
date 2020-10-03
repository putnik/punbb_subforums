FROM php:5.6-fpm

RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

RUN apt-get update
RUN apt-get install -y git
RUN docker-php-ext-install mysqli && \
    docker-php-ext-enable mysqli

RUN git clone https://github.com/punbb/punbb.git /var/www/html
RUN chown www-data:www-data -R /var/www/html
RUN chmod +w /var/www/html/cache

COPY html/config.php /var/www/html/config.php
