# RoadRunner binary, copied below — no local Go toolchain needed.
FROM ghcr.io/roadrunner-server/roadrunner:2024 AS roadrunner

FROM php:8.5-cli-alpine AS base

RUN apk update && apk add zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin
COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr

RUN set -eux; \
    install-php-extensions \
    zip \
    sockets \
    && rm -rf /tmp/*

ARG APP_USER_ID=1000
ARG APP_USER_NAME=appuser
RUN adduser -D -u ${APP_USER_ID} ${APP_USER_NAME}

USER ${APP_USER_NAME}
WORKDIR "/app"

EXPOSE 8080
CMD ["rr", "serve", "-c", ".rr/rr-kitchen.yaml"]


FROM base AS build-stage

WORKDIR "/app"
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist


FROM base AS dev

ARG APP_USER_NAME=appuser

USER root
RUN apk update
RUN apk add git vim bash
USER ${APP_USER_NAME}

WORKDIR "/app"

ENV APP_ENV=dev


FROM base AS prod

WORKDIR "/app"
COPY --from=build-stage /app/vendor/ vendor/
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p var/cache var/log

ENV APP_ENV=prod
