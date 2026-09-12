# Payment gateway demo

A deliberately small Symfony 7.4 application that demonstrates **Domain-Driven Design**,
**Hexagonal Architecture** and **CQRS** on two flows:

1. **Make a payment** (`/pay`) — Stripe (test mode) or PayPal (sandbox).
2. **Change the default provider** (`/admin/payment-provider`) — HTTP Basic protected.

It is not a production payment platform. See [`docs/architecture.md`](docs/architecture.md)
for the design rationale and the list of consciously omitted production concerns.

## Stack

| | |
|---|---|
| PHP | 8.4 (strict types) |
| Framework | Symfony 7.4 LTS |
| Persistence | Doctrine ORM + SQLite, **external XML mapping** (no ORM attributes in the domain) |
| Views | Twig + minimal per-provider JavaScript |
| Buses | Symfony Messenger, two synchronous buses (`command.bus`, `query.bus`) |
| Tests | PHPUnit 11 |

## Requirements

Docker + Docker Compose. Nothing else — PHP, the SQLite extension and Composer all live in
the image (`Dockerfile`).

> All commands below use `docker compose run --rm app …`. If you have a local PHP 8.4 with
> `pdo_sqlite`, you can drop that prefix.

## Setup

```bash
# 1. Configuration
cp .env.example .env.local          # then edit .env.local (see below)

# 2. Build + run. The image build installs the Composer dependencies, so there
#    is no separate `composer install` step.
docker compose up --build

# 3. Database schema (one time; the SQLite file lives in the `app_var` volume
#    and survives `docker compose down`)
docker compose run --rm app php bin/console doctrine:migrations:migrate --no-interaction

# open http://localhost:8000/pay  and  http://localhost:8000/admin/payment-provider
```

### Compose files

| File | Purpose |
|---|---|
| `docker-compose.yml` | Builds the **self-contained** image (source + dependencies baked in) and serves it. Real secrets are read from `.env.local` via `env_file`. |
| `compose.override.yml` | Auto-loaded by `docker compose`; bind-mounts the source for live editing in development. |

```bash
docker compose up --build                      # development (override applied)
docker compose -f docker-compose.yml up --build # run the image exactly as built
docker compose run --rm app composer install    # update dependencies in dev
```

> **DNS during build.** The image build runs `apt-get`, so it needs name
> resolution. `docker-compose.yml` sets `build.network: host` for this, which
> works on hosts whose Docker bridge network has no usable resolver (common when
> the host uses the `systemd-resolved` stub at `127.0.0.53`). A bare
> `docker build` needs `--network=host` added. The permanent host-wide fix is to
> put `{"dns": ["1.1.1.1", "8.8.8.8"]}` in `/etc/docker/daemon.json` and
> `sudo systemctl restart docker`.

### `.env.local`

`.env` holds committed non-secret defaults; put real values in `.env.local` (git-ignored).

| Variable | Notes |
|---|---|
| `APP_SECRET` | any 32+ hex chars — `openssl rand -hex 16` |
| `APP_BASE_URL` | `http://localhost:8000` for local use; used to build provider return URLs |
| `PAYMENT_DEFAULT_PROVIDER` | `stripe` or `paypal`; used only until a value is saved in the admin page |
| `ADMIN_USERNAME` | HTTP Basic user for `/admin` |
| `ADMIN_PASSWORD_HASH` | **hash**, not the password (see below) |
| `STRIPE_SECRET_KEY` / `STRIPE_PUBLISHABLE_KEY` | from <https://dashboard.stripe.com/test/apikeys> |
| `PAYPAL_CLIENT_ID` / `PAYPAL_CLIENT_SECRET` | sandbox app at <https://developer.paypal.com/dashboard/applications/sandbox> |
| `PAYPAL_BASE_URI` | `https://api-m.sandbox.paypal.com` |

Generate the admin password hash:

```bash
docker compose run --rm app php -r "echo password_hash('choose-a-password', PASSWORD_BCRYPT), PHP_EOL;"
```

## Sandbox testing

### Stripe (test mode)

1. Put your **test** keys in `.env.local`, keep `PAYMENT_DEFAULT_PROVIDER=stripe` (or select
   Stripe in the admin page).
2. Open `/pay`, submit email + amount.
3. The page mounts Stripe **Embedded Checkout**. Pay with test card `4242 4242 4242 4242`,
   any future expiry, any CVC, any postal code.
4. Stripe returns the browser to `/pay/complete`; the app calls
   `Checkout\Session::retrieve` **server-side** and only then shows "Payment confirmed".
5. Decline path: card `4000 0000 0000 0002`.

### PayPal (sandbox)

1. Put your sandbox client id/secret in `.env.local`, select PayPal in the admin page (or
   set `PAYMENT_DEFAULT_PROVIDER=paypal` before the first payment).
2. Open `/pay`, submit email + amount.
3. The page renders the PayPal buttons. Approve with a **sandbox buyer** account
   (Dashboard → Testing Tools → Sandbox Accounts).
4. `onApprove` posts to `/pay/complete`; the app calls `orders/{id}/capture`
   **server-side** and only a `COMPLETED` capture counts as paid.

If a provider call times out, the app shows *"outcome could not be confirmed"* and leaves
the payment `processing` — it never guesses, retries, or switches providers.

## Tests

```bash
docker compose run --rm app vendor/bin/phpunit                       # everything (87 tests)
docker compose run --rm app vendor/bin/phpunit --testsuite unit      # domain + application, fakes only
docker compose run --rm app vendor/bin/phpunit --testsuite architecture   # layer-boundary guard
docker compose run --rm app vendor/bin/phpunit --testsuite integration    # Doctrine mapping + HTTP smoke (SQLite)
```

Unit tests use fake gateways and an in-memory repository — **no credentials, no network**.
Integration tests build an in-memory SQLite schema with `SchemaTool`.

### What is verified automatically

- `Money` decimal parsing/formatting without floating-point arithmetic.
- `Payment` status transitions, including rejected illegal transitions and idempotent success.
- Gateway resolution: new payment → configured default; existing payment → its own stored provider.
- Payment initiation persists **before** the provider call; confirmed rejection → `failed`,
  timeout → stays `pending`.
- Completion uses the payment's **original** provider even after the default changed.
- Changing the default setting; saved value overrides the environment default.
- Unknown provider and provider errors produce clear application exceptions / uncertain results.
- Domain and Application source contains no `Symfony\`, `Doctrine\`, `Twig\`, `Stripe\`,
  `PayPal\`, `Psr\Http`, `GuzzleHttp\` reference (comments stripped before scanning).
- `/pay` renders; `/admin/payment-provider` returns 401 without and 200 with HTTP Basic.

### Manual checks (need sandbox credentials)

The real Stripe/PayPal round trips above are not automated (they need credentials and a
browser). Follow the *Sandbox testing* steps to exercise them end to end.

## Project layout

```
src/Payment/
  Domain/            # aggregate, value objects, status enum, repository contract - pure PHP
  Application/       # commands, queries, handlers (1:1), ports, gateway DTOs, resolver
  Infrastructure/    # Doctrine repositories + external mapping, Stripe/PayPal adapters, bus adapters
  Presentation/      # controllers, forms
config/
  services.yaml      # all wiring: ports->adapters, handler tags, gateway tag, env injection
  doctrine/          # external XML mapping (domain/ and infrastructure/)
  packages/          # doctrine, messenger (2 buses), security (http_basic on /admin)
migrations/          # initial schema
```
