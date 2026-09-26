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

RUN docker-php-ext-install \
    pdo_mysql \
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

RUN chown -R application:application /app \
    && chmod -R 775 storage bootstrap/cache public/storage