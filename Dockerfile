FROM talis/ubuntu:1404-latest

ENV DEBIAN_FRONTEND=noninteractive

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_HOME=/tmp

# Install php and ant
RUN apt-get update \
    && apt-get install --no-install-recommends -y \
        curl ca-certificates \
        php5-cli \
        php5-dev \
        php5-xdebug \
        php5-curl \
        php5-json \
        php-pear \
        git \
        unzip \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /var/talis-php
