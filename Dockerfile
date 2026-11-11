FROM php:8.4-apache

# Extensions used by the app: GD+WebP for image optimization, PDO/MySQL for the DB.
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libonig-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mysqli zip mbstring opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# Apache document root is /var/www/html; the repo's public/ contents are the web root.
# We copy the whole repo and point the docroot at public/.
COPY . /var/www/agrimall
RUN rm -rf /var/www/html && ln -s /var/www/agrimall/public /var/www/html \
 && chown -R www-data:www-data /var/www/agrimall

# config/config.php is gitignored; provide a placeholder that fails loudly if not replaced.
RUN if [ ! -f /var/www/agrimall/config/config.php ]; then \
      cp /var/www/agrimall/config/config.example.php /var/www/agrimall/config/config.php; \
    fi

EXPOSE 80
CMD ["apache2-foreground"]
