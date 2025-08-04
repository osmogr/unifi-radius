FROM php:8.1-apache

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Copy PHP configuration
COPY website/php.ini /usr/local/etc/php/conf.d/custom.ini

# Enable Apache modules and create logs directory
RUN a2enmod rewrite headers \
    && mkdir -p /var/log/apache2 \
    && chown -R www-data:www-data /var/log/apache2

# Copy Apache configuration
COPY website/apache.conf /etc/apache2/sites-available/000-default.conf

# Set proper permissions for web files (will be mounted as volume)
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]