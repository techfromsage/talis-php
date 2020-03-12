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

# Run Persona Locally

Integration tests run locally will make requests to Persona at `http://persona.talis.local`.

Before you begin, ensure you have established `DEVELOPMENT_WORK_DIR` and the `infra` repository, as per [the shared project instructions](https://github.com/talis/infra/wiki).

Manually run persona locally:

```bash
./docker-compose-dev.sh persona-server
```

# Create an OAuth Client and Secret

To build the talis-php docker container, you need to specify an oauth client and secret to use. This client should have `su` scope. It's not possibe to create a client with `su` scope via the API.

First - create a client:

```bash
curl -v -H "Authorization:Bearer $LOCAL_TOKEN" -d "{\"scope\":[\"su@test\"]}" http://persona.talis.local/clients
```

This will return a client:

```json
{"client_id":"BXLmKR79","client_secret":"zdlbESLEFGvxBw8k"}
```

Then connect to the mongo database the local persona is using and manually give the client `su` scope.

# Build talis-php Docker Container

Manually run a docker build:

```bash
docker build -t "talis/talis-php" --network=host --build-arg persona_oauth_client=<persona-user-goes-here> --build-arg persona_oauth_secret=<persona-secret-goes-here> .
```

`persona_oauth_client` = the persona user you want to use, "BXLmKR79" from the above example.

`persona_oauth_secret` =  the password to the user specified, "zdlbESLEFGvxBw8k" from the above example.

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

To create a docker container where you can run commands directly, for example to run individual tests:

```bash
docker-compose run local-dev
```

When connected run:

```bash
service redis-server start
source /etc/profile.d/*
```

You can the bun ant commands individually or run individual tests:

```bash
/vendor/bin/phpunit --filter testCreateUserThenPatchOAuthClientAddScope test/integration/
```
