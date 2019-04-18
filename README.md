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

# Build the development image

Clone the repo locally:

```bash
git clone https://github.com/talis/talis-php.git
cd talis-php
```

Manually run a docker build:

```bash
docker build -t "talis/talis-php" --network=host --build-arg persona_oauth_client=<client-name-goes-here> --build-arg persona_oauth_secret=<password-goes-here> .
```

Initialise the environment. Run the following command which will download the required libraries.

```bash
docker-compose run init
```

# When the above has built you can run the tests

Available test commands:

```bash
docker-compose run lint
docker-compose run codecheck
docker-compose run test
docker-compose run unittest
docker-compose run integrationtest
```
