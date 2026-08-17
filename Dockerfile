FROM php:8.2-apache

WORKDIR /var/www/html

COPY . /var/www/html/

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libwebp-dev zlib1g-dev libzip-dev unzip curl \
    && docker-php-ext-configure gd --with-webp --with-jpeg \
    && docker-php-ext-install gd zip \
    && a2enmod rewrite \
    && chown -R www-data:www-data /var/www/html \
    && rm -rf /var/lib/apt/lists/*

EXPOSE 80

CMD ["apache2-foreground"]
