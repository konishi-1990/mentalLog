# ---- MentalLog PHP-FPM (Laravel 11) ----
FROM php:8.3-fpm

# 実行ユーザ（ホストとの権限ずれを避けるため UID/GID を引数化）
ARG UID=1000
ARG GID=1000

# OSパッケージ & ビルド依存
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
        curl \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP拡張（PostgreSQL / Laravel向け）
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        bcmath \
        opcache

# pcov（テストカバレッジ計測用・軽量）
RUN pecl install pcov \
    && docker-php-ext-enable pcov

# Node.js 20（Vite ビルド用）
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# Composer（公式イメージからコピー）
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ホストUID/GIDに合わせて www-data を調整
RUN groupmod -o -g "${GID}" www-data \
    && usermod  -o -u "${UID}" -g "${GID}" www-data

# Composer / npm のキャッシュ・ホームを www-data 書込可能に
ENV COMPOSER_HOME=/var/www/.composer
ENV NPM_CONFIG_CACHE=/var/www/.npm
RUN mkdir -p /var/www/.composer /var/www/.npm \
    && chown -R www-data:www-data /var/www

WORKDIR /var/www/html

# PHP設定
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
