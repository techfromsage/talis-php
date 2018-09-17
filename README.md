talis-php [![build status](https://travis-ci.org/talis/talis-php.svg?branch=master)](https://travis-ci.org/talis/talis-php)

=========

# Development Version - Not For General Use

This is a development version of the new talis-php version. It is not yet intended for general use.
This early version pulls existing individual client libraries into one single library with minimal
changes.

Before releasing for general usage there will be major changes to the API. The library will move
away from the use of internal Talis project names like Persona, Critic, Babel and Echo etc.
Instead it will use more externally relavant names like ```ListReviews``` and ```Files```.
The API will also move to a more domain driven design rather than the service driven design
of the individual libraries

See issue: https://github.com/talis/talis-php/issues/2 and Milestone: https://github.com/talis/talis-php/milestone/1


## Contributing

A Dockerfile is provided to make it easy to get a local development environment
up and running to develop and test changes. Follow these steps:

```bash

# Build the development image

git clone https://github.com/talis/talis-php.git
cd talis-php
docker build -t "talis-php:dev" --build-arg git_oauth_token=<your github oauth token> --build-arg persona_oauth_client=<your oauth client> --build-arg persona_oauth_secret=<your oauth client secret> .

# When the above has built successfully you can run and connect to the container
docker run -v /path/on/host/machine/to/talis-php:/var/talis-php -i -t echo-php-client:dev /bin/bash

# Then inside the container

service redis-server start
ant init
ant test
```

