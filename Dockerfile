# Production Dockerfile for Thamani High School Application
FROM php:8.2-apache

# Install system dependencies & PostgreSQL development libraries
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pgsql gd zip curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure custom PHP runtime directives
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "date.timezone = Africa/Kampala" >> /usr/local/etc/php/conf.d/uploads.ini

# Set Working Directory
WORKDIR /var/www/html

# Copy application files into container
COPY . /var/www/html/

# Create required upload storage folders & assign permissions
RUN mkdir -p /var/www/html/calendar_docs \
             /var/www/html/gallery_images \
             /var/www/html/library \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
