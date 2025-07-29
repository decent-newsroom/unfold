FROM php:8.2-cli

# Install cron and Redis PHP extension dependencies
RUN apt-get update && apt-get install -y \
    cron \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    libonig-dev


# Set working directory
WORKDIR /var/www/html

# Install Symfony CLI tools (optional)
# RUN curl -sS https://get.symfony.com/cli/installer | bash

# Copy cron and script
COPY crontab /etc/cron.d/app-cron
COPY index_articles.sh /index_articles.sh

# Set permissions
RUN chmod 0644 /etc/cron.d/app-cron && \
    chmod +x /index_articles.sh

# Apply cron job
RUN crontab /etc/cron.d/app-cron

# Run cron in the foreground
CMD ["cron", "-f"]
