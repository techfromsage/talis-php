talis-php [![build status](https://travis-ci.org/talis/talis-php.svg?branch=master)](https://travis-ci.org/talis/talis-php)

---

# Development Version - Not For General Use

This is a development version of the new talis-php version. It is not yet intended for general use.
This early version pulls existing individual client libraries into one single library with minimal
changes.

Before releasing for general usage there will be major changes to the API. The library will move
away from the use of internal Talis project names like Persona, Critic, Babel and Echo etc.
Instead it will use more externally relevant names like ```ListReviews``` and ```Files```.
The API will also move to a more domain driven design rather than the service driven design
of the individual libraries

See issue: https://github.com/talis/talis-php/issues/2 and Milestone: https://github.com/talis/talis-php/milestone/1

## Contributing

A Dockerfile is provided to make it easy to get a local development environment
up and running to develop and test changes. Follow these steps:

# Set up

Clone the repo locally:

```bash
git clone https://github.com/talis/talis-php.git
cd talis-php
```

## Run Persona Locally

Integration tests run locally will make requests to Persona at `http://persona.talis.local`.

Before you begin, ensure you have established `DEVELOPMENT_WORK_DIR` and the `infra` repository, as per [the shared project instructions](https://github.com/talis/infra/wiki).

Manually run persona locally:

```bash
./docker-compose-dev.sh persona-server
```

## Custom OAuth Client and Secret

If you are running Persona using the instructions above you can skip this step. If you want to create you own OAuth client, read on.

To run talis-php tests, the OAuth client must have `su` scope. It's not possible to create a client with `su` scope via the API.

1. Create a client:

    ```bash
    curl -H "Authorization: Bearer $(persona-token)" -d "{\"scope\":[\"su@test\"]}" http://persona.talis.local/clients
    ```

    This will return a client, e.g.:

    ```json
    {"client_id":"BXLmKR79","client_secret":"zdlbESLEFGvxBw8k"}
    ```
2. Connect to the mongo database the local persona is using and manually give the client `su` scope.

    ```bash
    cd $DEVELOPMENT_WORK_DIR/infra
    docker compose exec mongo32 mongo
    # in mongo shell
    use persona
    db.oauth_clients.updateOne({ client_id: "<client_id>" }, { $addToSet: { scope: "su" } })
    db.oauth_clients.find({ client_id: "<client_id>" }).pretty()
    ```
3. Create an `.env` file with required environment variables:

    ```bash
    cd $DEVELOPMENT_WORK_DIR/talis-php
    cat > .env <<EOL
    PERSONA_TEST_HOST=http://persona.talis.local
    PERSONA_TEST_OAUTH_CLIENT=<client_id>
    PERSONA_TEST_OAUTH_SECRET=<client_secret>
    EOL
    ```

    Remember to replace `PERSONA_OAUTH_CLIENT` and `PERSONA_OAUTH_SECRET` with values of `client_id` and `client_secret` respectively.

## Install dependencies and build the Docker image

Run the following command which should build the Docker image (if it's missing) and will download the required libraries:

```bash
docker compose run --rm init
```

If you want to rebuild the Docker image at any point, run:

```bash
docker compose build
```

# Running tests

Available test commands:

```bash
docker compose run --rm lint
docker compose run --rm test
docker compose run --rm unittest
docker compose run --rm integrationtest
```

To create a docker container where you can run commands directly, for example to run individual tests:

```bash
docker compose run --rm local-dev
```

You can then run ant commands individually or run individual tests:

```bash
/vendor/bin/phpunit --filter testCreateUserThenPatchOAuthClientAddScope test/integration/
```

Additionally we provide tools to run static analysis on the code base:

```bash
docker compose run --rm code-check
docker compose run --rm analyse
```
