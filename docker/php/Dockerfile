# ---- MentalLog PHP-FPM (Laravel) マルチステージ ----
# base        : 開発・本番共通の PHP ランタイム + 拡張 + composer + node
# development : ローカル開発用（ソースはボリュームマウント / pcov 追加）
# build       : 本番資産のビルド（composer install --no-dev / vite build）
# production  : 本番 PHP-FPM（build の成果物を焼き込み）
# nginx       : 本番 nginx（build の public/ を焼き込み・静的配信）

# ─── base ────────────────────────────────────────────────
FROM php:8.3-fpm AS base

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

EXPOSE 9000

# ─── development ─────────────────────────────────────────
FROM base AS development

# pcov（テストカバレッジ計測用・軽量）
RUN pecl install pcov \
    && docker-php-ext-enable pcov

USER www-data
CMD ["php-fpm"]

# ─── build（本番資産のビルド）─────────────────────────────
FROM base AS build

# 依存の解決を先に行いレイヤキャッシュを効かせる
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY package.json package-lock.json ./
RUN npm ci

# アプリ本体をコピー
COPY . .

# composer オートロード最適化（artisan スクリプトは本番 .env が無いため抑止）
RUN composer dump-autoload --optimize --no-dev

# Vite で本番アセットをビルド → public/build を生成
RUN npm run build

# route/view キャッシュを焼き込み（config は .env 依存のためデプロイ時に生成）
RUN php artisan route:cache \
    && php artisan view:cache

# ─── production（本番 PHP-FPM）────────────────────────────
FROM base AS production

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html

USER www-data
CMD ["php-fpm"]

# ─── nginx（本番 静的配信 + FPM プロキシ）─────────────────
FROM nginx:1.27-alpine AS nginx

# ビルド済みの公開ディレクトリ（Vite 資産含む）を焼き込み
COPY --from=build /var/www/html/public /var/www/html/public
COPY docker/nginx/default.prod.conf /etc/nginx/conf.d/default.conf
