FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && (a2dismod mpm_event || true) \
    && (a2dismod mpm_worker || true) \
    && rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* \
    && a2enmod mpm_prefork rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY AirshedProject/ /var/www/AirshedProject/
COPY PHPMailer-master/ /var/www/PHPMailer-master/

ENV APACHE_DOCUMENT_ROOT=/var/www/AirshedProject
RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/AirshedProject
EXPOSE 8080

# Railway provides the listening port through the PORT environment variable.
CMD ["sh", "-c", "sed -ri \"s/^Listen 80$/Listen ${PORT:-8080}/; s/<VirtualHost \\*:80>/<VirtualHost *:${PORT:-8080}>/\" /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf && apache2-foreground"]
