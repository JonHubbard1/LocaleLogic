FROM php:8.3-fpm AS base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev libzip-dev libpng-dev libjpeg-dev \
    libfreetype6-dev nginx nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip gd bcmath opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for caching
COPY composer.json composer.lock ./

# Configure Flux UI Pro auth and install dependencies
ARG FLUX_USERNAME=jon@jonhubbard.org
ARG FLUX_LICENSE=7b668b2b-338d-4618-a9cb-a1eae76b2725
RUN composer config http-basic.composer.fluxui.dev "$FLUX_USERNAME" "$FLUX_LICENSE" \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy package files and install npm deps
COPY package.json package-lock.json ./
RUN npm ci

# Copy the rest of the application
COPY . .

# Build assets
RUN npm run build

# Run Laravel post-install
RUN composer run-script post-autoload-dump 2>/dev/null || true

# Set permissions
RUN chmod -R 775 storage bootstrap/cache 2>/dev/null || true \
    && chown -R www-data:www-data /app

# Configure nginx
COPY deploy-scripts/nginx.conf /etc/nginx/sites-available/default 2>/dev/null || true
RUN cat > /etc/nginx/sites-available/default << 'NGINX'
server {
    listen 80;
    server_name _;
    root /app/public;
    index index.php index.html;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

# Start script
RUN cat > /app/start.sh << 'STARTSH'
#!/bin/bash
cd /app

# Run migrations if needed
php artisan migrate --force 2>/dev/null || true

# Clear and cache config
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true

# Start PHP-FPM and Nginx
php-fpm -D
nginx -g "daemon off;"
STARTSH
RUN chmod +x /app/start.sh

EXPOSE 80

CMD ["/app/start.sh"]
