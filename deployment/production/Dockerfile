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
    icu-dev && \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers && \
    docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip && \
    pecl install redis --configureoptions 'enable-redis-igbinary="no" enable-redis-lzf="no" enable-redis-zstd="no" enable-redis-msgpack="no" enable-redis-lz4="no"' && \
    docker-php-ext-enable redis && \
    apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

CMD ["/bin/bash"]
