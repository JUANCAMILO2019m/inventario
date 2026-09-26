# Etapa 1: compilar los assets del frontend
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

# Etapa 2: imagen final con PHP + Nginx
FROM webdevops/php-nginx:8.3-alpine

RUN apk add --no-cache \
    oniguruma-dev \
    libzip-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    freetype-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg

RUN docker-php-ext-install \
    mbstring \
    bcmath \
    gd \
    exif \
    zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV WEB_DOCUMENT_ROOT=/app/public
ENV APP_ENV=production

WORKDIR /app
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer install --no-interaction --optimize-autoloader --no-dev

RUN mkdir -p public/storage/products \
    && chown -R application:application /app \
    && chmod -R 775 storage bootstrap/cache public/storage