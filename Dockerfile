FROM php:8.2-apache

WORKDIR /var/www/html

ENV PORT=10000

COPY . /var/www/html/

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libwebp-dev zlib1g-dev libzip-dev unzip curl \
    && docker-php-ext-configure gd --with-webp --with-jpeg \
    && docker-php-ext-install gd zip \
    && a2enmod rewrite \
    && sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf \
    && sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf \
    && chown -R www-data:www-data /var/www/html \
    && rm -rf /var/lib/apt/lists/*

EXPOSE 10000

CMD ["sh", "-c", "apache2ctl -D FOREGROUND"]
