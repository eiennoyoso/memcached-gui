FROM nginx/unit:1.26.1-php8.1

RUN docker-php-ext-install sockets

COPY ./src /var/www/src
COPY ./public /var/www/public
COPY ./docker/config.json /docker-entrypoint.d/config.json