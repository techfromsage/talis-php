FROM talis/ubuntu:1404-latest

MAINTAINER Malcolm Landon "ml@talis.com"

ENV DEBIAN_FRONTEND noninteractive

ARG git_oauth_token
ARG persona_oauth_client
ARG persona_oauth_secret

RUN apt-get update && apt-get upgrade -y

RUN apt-get install -y --force-yes curl apt-transport-https && curl -L http://apt.talis.com:81/public.key | sudo apt-key add - && apt-get update

# Install php and ant
RUN apt-get install --no-install-recommends -y \
		curl ca-certificates \
		php5-cli \
		php5-dev \
		php5-xdebug \
		php5-curl \
		php5-json \
		php-pear \
    ant \
    git

# Install redis and cli, staring the service
RUN apt-get install --no-install-recommends -y \
    redis-tools \
    redis-server

# Install composer
RUN curl https://getcomposer.org/installer | php -- && mv composer.phar /usr/local/bin/composer && chmod +x /usr/local/bin/composer

RUN composer config -g github-oauth.github.com $git_oauth_token

# Tidy up
RUN apt-get -y autoremove && apt-get clean && apt-get autoclean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mkdir -p /var/talis-php
COPY . /var/talis-php

RUN echo 'export PERSONA_TEST_HOST=https://staging-users.talis.com' >> /root/.bashrc
RUN echo 'export PERSONA_TEST_OAUTH_CLIENT='$persona_oauth_client >> /root/.bashrc
RUN echo 'export PERSONA_TEST_OAUTH_SECRET='$persona_oauth_secret >> /root/.bashrc

WORKDIR /var/talis-php

RUN ant init

CMD /bin/bash -c "service redis-server start && ant test"
