# Vehicle Debt API

A stateless Laravel 13 API for consulting vehicle debts from two providers, calculating interest, and simulating payment options. It uses PHP 8.4+, integer cents for monetary arithmetic, Nginx, PHP-FPM, and Redis for the distributed provider circuit breaker. There is no application database, authentication, or queue.

## Install Docker on Ubuntu

The commands below install Docker Engine, the Docker CLI, Buildx, and the Compose plugin from Docker's official Ubuntu repository. Docker currently lists Ubuntu 26.04, 24.04, and 22.04 as supported releases. See the [official Docker Engine installation guide](https://docs.docker.com/engine/install/ubuntu/) for other operating systems and updates.

```sh
sudo apt update
sudo apt install ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

sudo tee /etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}")
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF

sudo apt update
sudo apt install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl status docker --no-pager
sudo docker run --rm hello-world
sudo docker compose version
```

The Compose plugin is included in that package install. Docker commands below use `sudo`, which works with the default Engine socket permissions. To run Docker commands without `sudo`, add your user to the `docker` group:

```sh
sudo usermod -aG docker "$USER"
```

Then log out and back in, or run `newgrp docker` to apply the new group membership in the current terminal. Verify access with `docker ps`. **Security note:** membership in the `docker` group grants root-level privileges. See Docker's [Linux post-installation steps](https://docs.docker.com/engine/install/linux-postinstall/) for details.

## Install Composer dependencies for a clone

To populate `vendor/` for IDE autocomplete without installing PHP or Composer on the host, run this from the project root:

```sh
docker run --rm \
  --user "$(id -u):$(id -g)" \
  --volume "$PWD:/app" \
  --workdir /app \
  composer:2 install
```

This runs Composer in a temporary container and writes the dependencies into the cloned project. If Docker reports a socket permission error, prefix the command with `sudo` (for example, `sudo docker run ...`). The first install also creates `composer.lock` if it is absent; commit that lock file so other developers install the same dependency versions. You can skip this step when you only want to run the application: `docker compose up --build -d` installs the runtime dependencies while building the app image.

## Build and run the project

From the project directory, create the local Compose environment file:

```sh
cp .env.example .env
```

Edit `.env` and set `APP_KEY` plus the real endpoints for `PROVIDER_A_URL` and `PROVIDER_B_URL`. Both provider URLs must be reachable from the app container. `PROVIDER_A_URL` should serve the documented JSON contract and `PROVIDER_B_URL` the documented XML contract. If the provider URLs are unset or unreachable, the app still starts, but API requests return HTTP 503 after both providers fail.

Generate a Laravel key and add the output to `.env` as `APP_KEY=base64:<value>`:

```sh
openssl rand -base64 32
```

### Run with local mock providers

For a working local example without external provider accounts, open a second terminal in the project directory and start the included Python 3 mock server:

```sh
python3 tools/mock_providers.py
```

Set these values in `.env`:

```dotenv
PROVIDER_A_URL=http://host.docker.internal:8001/provider-a
PROVIDER_B_URL=http://host.docker.internal:8001/provider-b
VEHICLE_DEBTS_AS_OF=2024-05-10T00:00:00Z
```

Compose maps `host.docker.internal` to the host gateway, so the PHP-FPM container can call this server on Linux as well as Docker Desktop. Leave the server running while you start Compose in the next step. The fixture dates are anchored to `2024-05-10` UTC and include overdue and future debts, two IPVA debts, and one MULTA debt. This mock server is for local development only; it has no authentication and returns fixed data.

Build the images and start Nginx, PHP-FPM, and Redis in the background:

```sh
sudo docker compose up --build -d
```

Compose starts Redis automatically and keeps it on the private Compose network; the Laravel container connects to it at `redis:6379`. Check that it is ready with:

```sh
sudo docker compose exec redis redis-cli ping
```

The expected response is `PONG`. You can start Redis by itself with `sudo docker compose up -d redis` or inspect its logs with `sudo docker compose logs redis`. The Compose Redis service does not publish port 6379 on the host.

Check the containers and application logs:

```sh
sudo docker compose ps
sudo docker compose logs -f app nginx
```

The API is available at `http://localhost:8080`. Check the Laravel health route:

```sh
curl -i http://localhost:8080/up
```

Laravel's built-in `/up` health route is a status check and may return an HTML body; a `200` response means the app booted successfully. The Postman test checks the status code rather than expecting JSON.

Try a debt consultation. With a normal plate, Provider A returns JSON:

```sh
curl -i -X POST http://localhost:8080/api/vehicle-debts \
  -H 'Content-Type: application/json' \
  -d '{"plate":"ABC1234","debt_type":"TOTAL"}'
```

Use `ZZZ0000` to simulate Provider A returning HTTP 503; Provider B then returns the same sample debts as XML:

```sh
curl -i -X POST http://localhost:8080/api/vehicle-debts \
  -H 'Content-Type: application/json' \
  -d '{"plate":"ZZZ0000"}'
```

### Test the circuit breaker

The breaker opens for a provider after 5 failed API consultations within 30 seconds. The first five requests to `ZZZ0000` still fall back to Provider B; the sixth request demonstrates that the open circuit skips Provider A. Reset Redis first so earlier calls do not affect the count:

```sh
sudo docker compose restart redis

for i in {1..5}; do
  curl -s -X POST http://localhost:8080/api/vehicle-debts \
    -H 'Content-Type: application/json' \
    -d '{"plate":"ZZZ0000"}' \
    | python3 -c 'import json, sys; print(json.load(sys.stdin)["provider"])'
done
```

Each request should print `provider_b`. Send one more request:

```sh
curl -s -X POST http://localhost:8080/api/vehicle-debts \
  -H 'Content-Type: application/json' \
  -d '{"plate":"ZZZ0000"}'
```

It should still return `provider_b`, while the app logs show Provider A being skipped:

```sh
sudo docker compose logs --since=1m app | grep vehicle_debt.provider_circuit_open
```

To verify both providers opening, restart Redis again, send five `ZZZ9999` requests within 30 seconds, then send a sixth. The mock makes both providers fail for this plate, so the sixth request should return HTTP 503 with both providers skipped. The circuit remains open for 30 seconds; after that, the next call is allowed as a half-open probe. Redis is ephemeral in this Compose setup, so restarting it clears the breaker state between runs.

Use `ZER0000` to get a valid zero-debt response, and use `ZZZ9999` to make both mock providers return HTTP 503 and verify the API's 503 response:

```sh
curl -i -X POST http://localhost:8080/api/vehicle-debts \
  -H 'Content-Type: application/json' \
  -d '{"plate":"ZZZ9999"}'
```

Stop the containers while keeping the built images:

```sh
sudo docker compose down
```

To rebuild after changing source files, use `sudo docker compose up --build -d` again. The project does not need host PHP, Composer, or MySQL for the Docker workflow. Redis is started by Compose and is only used for shared circuit-breaker state; if Redis becomes unreachable, the breaker fails open and normal provider calls and fallback continue.

For local development where Laravel runs directly on the host, start a Redis container bound only to localhost:

```sh
sudo docker run -d --name vehicle-debt-redis \
  -p 127.0.0.1:6379:6379 \
  redis:7-alpine
sudo docker exec vehicle-debt-redis redis-cli ping
```

Set these Redis values in `.env` for the host-run Laravel process (the PHP Redis extension must be installed):

```dotenv
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CIRCUIT_BREAKER_ENABLED=true
```

Stop and remove this local Redis container with `sudo docker rm -f vehicle-debt-redis`. For local development without Docker, PHP 8.4+, Composer 2, the DOM, SimpleXML, cURL, mbstring, and Redis extensions are required; then run `composer install`, `php artisan key:generate`, and `php artisan serve` after configuring the provider URLs and Redis in `.env`.

## API

`POST /api/vehicle-debts` accepts JSON:

```json
{"plate":"ABC1234","debt_type":"TOTAL"}
```

`debt_type` is optional and defaults to `TOTAL`. Supported values are `TOTAL`, `SOMENTE_IPVA`, and `SOMENTE_MULTA`. Plates accept old Brazilian (`ABC1234`) and Mercosur (`ABC1D23`) formats; punctuation and spaces are removed before validation. Unknown request fields are rejected. JSON request bodies are limited to 16 KiB by both Nginx and the application.

The response contains canonical debts with original amount, due date, overdue days, interest, total and payment options, as well as the aggregate total and options. Every monetary value is a decimal string with two places. Empty provider debt lists return a successful zero total. Invalid plates return HTTP 400, unknown debt types return HTTP 422, and an outage at both providers returns HTTP 503.

## Postman collection

Import `postman/vehicle-debt-api.postman_collection.json` and `postman/vehicle-debt-local.postman_environment.json` into Postman, then select **Vehicle Debt API - Local**. Start the mock provider script and Docker Compose app as described above, then run the collection in Postman's Collection Runner. Each request has assertions; expected error responses (400, 404, 413, 422, and 503) count as passing when the status and error details match.

The collection checks the health route, total debt search and payment simulations, both debt-type filters, zero debts, Provider A JSON success, Provider A failure with Provider B XML fallback, invalid/missing input, unknown fields and debt types, incorrect content type, oversized bodies, unknown route/method behavior, and both providers being unavailable. Payment options are part of the debt search response; this application does not execute payments and has no separate payment endpoint.

If the `ZER0000` request returns debts instead of an empty list, restart the Python mock server so it loads the current fixture, and confirm the app's `.env` points to the mock URLs above. Check the fixture directly on the host with `curl 'http://localhost:8001/provider-a?plate=ZER0000'`; it should return `{"debts":[]}`. After changing `.env`, recreate the app container so Compose passes the updated provider URLs: `sudo docker compose up -d --force-recreate app`.

## Provider contracts

The adapters intentionally isolate provider-specific transport and parsing. Configure one URL per provider; both receive `GET <URL>?plate=<plate>`.

Provider A must return JSON shaped as:

```json
{"debts":[{"id":"debt-1","type":"IPVA","amount":"100.00","due_date":"2025-01-31"}]}
```

Provider B must return XML shaped as:

```xml
<debts><debt><id>debt-1</id><type>IPVA</type><amount>100.00</amount><due_date>2025-01-31</due_date></debt></debts>
```

`<debts/>` is a valid empty response. Both formats normalize to the same domain fields. Provider debt types are `IPVA` and `MULTA`; amounts must be decimal strings and due dates must be `YYYY-MM-DD`. A successful provider response is authoritative for that request; provider reconciliation is intentionally out of scope because the API has no defined identity or conflict-resolution rules across sources.

## Calculation decisions

- Money is stored and operated on as signed integer cents. Decimal provider values are rounded to cents using HALF_UP at ingestion.
- IPVA interest is simple interest of 0.33% per overdue calendar day and is capped at 20% of the original principal. MULTA interest is simple interest of 1% per overdue day with no cap. Future and current debts have zero interest.
- PIX applies a 5% discount to the debt total.
- Credit-card 1x has no financing charge. Six and twelve installments use the fixed-payment Price/PMT formula at a 2.5% monthly rate. The installment is rounded HALF_UP to cents; the displayed plan total is installment amount times installment count.
- Date-only due dates and overdue-day comparisons use UTC calendar dates. Set `VEHICLE_DEBTS_AS_OF=2024-05-10T00:00:00Z` to pin the API to the home-test reference date; the local `.env.example`, Compose app and Postman fixtures use this value. Leave it unset in production to use the current UTC date.
- The first provider that returns a syntactically valid payload is used. Failures are logged with a masked plate; full plates are returned to the API caller but are not written to logs.

## Architecture and trade-offs

The domain layer contains plate, debt, money, interest rules, and payment strategies. Provider adapters implement a port and the application use case handles ordered fallback, normalization, calculation, and simulation. HTTP concerns stay in the controller and middleware. This keeps provider formats and pricing policies replaceable without introducing persistence or asynchronous infrastructure for a synchronous lookup.

The service is stateless and can scale horizontally behind a load balancer. Provider resilience combines short timeouts, bounded retries, ordered fallback, and a Redis-backed circuit breaker whose state is shared across app replicas. Each provider has an independent circuit: after 5 failures in a 30-second window it opens for 30 seconds, then allows one half-open probe at a time. A successful response closes and resets the circuit; a failed probe reopens it. These settings can be tuned with `CIRCUIT_BREAKER_FAILURE_THRESHOLD`, `CIRCUIT_BREAKER_FAILURE_WINDOW_SECONDS`, `CIRCUIT_BREAKER_OPEN_SECONDS`, and `CIRCUIT_BREAKER_PROBE_LEASE_SECONDS`. Circuit-breaker storage errors are logged and fail open so Redis trouble does not block debt consultations. Redis is used only for this transient state; there is no application database. For future asynchronous workloads on AWS, SQS is a suitable queue; Kubernetes/EKS can be considered if operational scale warrants it. CI builds the image and runs Composer validation, Pint, PHPStan, and PHPUnit. An AWS deployment can use GitHub OIDC rather than long-lived AWS keys.

## Quality checks

```sh
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit
docker build --target app -t vehicle-debt-api .
```

Tests cover money rounding, interest rules and cap, non-overdue amounts, payment plans, JSON success, XML fallback and empty results, provider outages, skipping a provider with an open circuit, validation, unknown fields/types, filtering, and duplicate debt types.

## Future improvements

- Add real provider-specific schemas, authentication, and contract tests as provider documentation becomes available.
- Define stable provider debt identifiers and a reconciliation policy if cross-provider comparison is required.
- Add provider health metrics and tracing when operational data justifies them.
- Add rate limiting, API authentication, and request correlation identifiers before exposing a public production endpoint.
- Use SQS for any future asynchronous processing; preserve stateless application replicas for horizontal scaling.
