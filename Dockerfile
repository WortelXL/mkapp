FROM php:8.3-apache

# pdo_mysql is nodig voor de databaseverbinding
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

# Aanbevolen PHP-instellingen voor een klein web-formulier zoals dit
RUN { \
        echo 'upload_max_filesize=10M'; \
        echo 'post_max_size=10M'; \
        echo 'memory_limit=128M'; \
    } > /usr/local/etc/php/conf.d/meldkamer.ini

WORKDIR /var/www/html

COPY . /var/www/html/

# config.php wordt normaal gesproken niet meegebouwd (zie .dockerignore);
# als hij ontbreekt gebruiken we het voorbeeldbestand als basis. Alle
# waarden worden op het moment zelf toch overschreven door de
# omgevingsvariabelen uit docker-compose.yml.
RUN [ -f config.php ] || cp config.example.php config.php \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
