FROM dunglas/frankenphp:1.13.2-php8.4.27-alpine

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

# 以非 root 執行（usongrat 未開 userns-remap，container 內 root = 主機 root）。
# UID/GID 固定且不與主機既有帳號重疊；主機上 storage/、bootstrap/cache/ 需 chown 成同一組
# （deploy.sh fix-perms，見 docker-compose.yml 的 APP_UID/APP_GID）。
ARG APP_UID=10001
ARG APP_GID=10001
RUN addgroup -g "${APP_GID}" -S wallet && \
    adduser -u "${APP_UID}" -G wallet -S -D -h /home/wallet -s /sbin/nologin wallet && \
    # Caddy（FrankenPHP）的 XDG_DATA_HOME=/data、XDG_CONFIG_HOME=/config 要可寫
    chown -R "${APP_UID}:${APP_GID}" /data /config && \
    # supervisord 以非 root 執行：socket / pid / log 放在 wallet 可寫的目錄
    install -d -o "${APP_UID}" -g "${APP_GID}" -m 0750 /run/supervisor /var/log/supervisor && \
    # 讓不帶 -c 的 supervisorctl（deploy.sh、docker exec）也連到同一個 socket
    ln -sf /var/www/html/deployment/production/supervisor/supervisord.conf /etc/supervisord.conf

USER ${APP_UID}:${APP_GID}

CMD ["/bin/bash"]
