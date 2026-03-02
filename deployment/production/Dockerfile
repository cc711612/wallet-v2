FROM dunglas/frankenphp:php8.4-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    curl \
    git \
    supervisor \
    unzip \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    linux-headers \
    $PHPIZE_DEPS

RUN docker-php-ext-install \
    bcmath \
    intl \
    mbstring \
    pcntl \
    pdo_mysql \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist && \
    php artisan package:discover --ansi || true

RUN chmod +x /var/www/html/deployment/production/entrypoint-web.sh && \
    chmod +x /var/www/html/deployment/production/entrypoint-worker.sh

ENTRYPOINT ["/bin/bash"]
