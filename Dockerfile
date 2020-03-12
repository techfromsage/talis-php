FROM talis/ubuntu:1404-latest

ENV DEBIAN_FRONTEND noninteractive

ARG persona_oauth_client
ARG persona_oauth_secret

RUN apt-get update \
    && apt-get install -y --force-yes curl apt-transport-https \
    && curl -L http://apt.talis.com:81/public.key | sudo apt-key add - \
    && apt-get update \
    && apt-get upgrade -y

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

# Tidy up
RUN apt-get -y autoremove && apt-get clean && apt-get autoclean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mkdir -p /var/talis-php
COPY . /var/talis-php

RUN echo "export PERSONA_TEST_HOST='http://persona.talis.local'" >> /etc/profile.d/test.sh \
    && echo "export PERSONA_TEST_OAUTH_CLIENT='$persona_oauth_client'" >> /etc/profile.d/test.sh \
    && echo "export PERSONA_TEST_OAUTH_SECRET='$persona_oauth_secret'" >> /etc/profile.d/test.sh \
    && chmod 775 /etc/profile.d/test.sh

WORKDIR /var/talis-php

RUN ant init

CMD /bin/bash -c "service redis-server start && source /etc/profile.d/* && ant test"
