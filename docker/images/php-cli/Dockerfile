FROM php:7.2.9-cli

RUN docker-php-ext-install mysqli pdo_mysql bcmath

ENTRYPOINT ["docker-php-entrypoint"]

CMD ["php", "-a"]

# docker build -t php-cli-dev:0.2 .