FROM php:8.3-cli

# Installa dipendenze sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    antiword \
    poppler-utils \
    tesseract-ocr \
    tesseract-ocr-ita \
    tesseract-ocr-eng \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip gd pcntl \
    && rm -rf /var/lib/apt/lists/*

# Installa Xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Configura PHP e Xdebug
RUN echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize=256M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size=256M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9013" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.idekey=PHPSTORM" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Installa Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copia dipendenze prima per sfruttare la cache Docker
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader

# Copia il resto dell'applicazione
COPY . .

# Completa l'installazione
RUN composer dump-autoload --optimize

# Permessi storage
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
