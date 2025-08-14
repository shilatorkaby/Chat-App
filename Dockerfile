FROM php:8.2-apache

# PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql \
 && docker-php-ext-enable mysqli pdo_mysql

# curl (Brevo)
RUN apt-get update && apt-get install -y libcurl4-openssl-dev \
 && docker-php-ext-install curl && docker-php-ext-enable curl

RUN apt-get update && apt-get install -y default-mysql-client

# Apache & PHP timezone
RUN a2enmod rewrite
ENV TZ=Asia/Jerusalem
RUN echo "date.timezone=${TZ}" > /usr/local/etc/php/conf.d/timezone.ini

# SQL files
COPY sql /app/sql


# Tools + entrypoint
COPY tools /var/www/html/tools
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["bash","/usr/local/bin/entrypoint.sh"]
