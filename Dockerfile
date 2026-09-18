FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

COPY AirshedProject/ /var/www/AirshedProject/
COPY PHPMailer-master/ /var/www/PHPMailer-master/

WORKDIR /var/www/AirshedProject
EXPOSE 8080

# Railway provides the listening port through the PORT environment variable.
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /var/www/AirshedProject"]
