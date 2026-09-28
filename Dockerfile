FROM php:8.3-apache

RUN a2enmod headers rewrite \
    && printf '%s\n' \
      'ServerSignature Off' \
      'ServerTokens Prod' \
      'CustomLog /dev/null combined' \
      'ErrorLog /dev/stderr' \
      > /etc/apache2/conf-available/privacy.conf \
    && a2enconf privacy

WORKDIR /var/www/html
COPY public/ /var/www/html/

RUN mkdir -p /var/www/storage \
    && chown -R www-data:www-data /var/www/html /var/www/storage \
    && chmod -R 755 /var/www/html \
    && chmod 770 /var/www/storage

ENV PRESENCA_STORAGE=/var/www/storage

EXPOSE 80
