FROM php:8.1-cli-alpine

ARG COMPOSER_VERSION=2.4.1
ARG MSGLINT_VERSION=1.0.4

# msglint, composer, php-intl, xdebug
RUN apk add --no-cache icu-dev \
  && apk add --no-cache --virtual .build-deps \
    autoconf \
    g++ \
    make \
  && docker-php-ext-install intl \
  && pecl install xdebug \
  && wget -qO /usr/bin/composer https://github.com/composer/composer/releases/download/$COMPOSER_VERSION/composer.phar \
  && chmod +x /usr/bin/composer \
  && wget https://github.com/jchook/msglint/archive/refs/tags/v$MSGLINT_VERSION.tar.gz \
  && tar xzf v$MSGLINT_VERSION.tar.gz \
  && cd msglint-$MSGLINT_VERSION \
  && make \
  && cp msglint /usr/bin \
  && cd - \
  && rm -rf msglint-$MSGLINT_VERSION v$MSGLINT_VERSION.tar.gz \
  && apk del .build-deps

RUN docker-php-ext-enable xdebug

VOLUME /app
WORKDIR /app
CMD ["/usr/bin/sh"]

