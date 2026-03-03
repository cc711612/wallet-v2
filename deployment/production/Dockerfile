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
    linux-headers && \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS && \
    docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip && \
    pecl install redis && \
    docker-php-ext-enable redis && \
    apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

ENTRYPOINT ["/bin/bash"]
