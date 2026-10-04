# EAV Ledger API

Laravel billing API for customers, invoices, payments, receipts, and auditable business workflows.

## Status

The Laravel 13 API is deployed from `main` to Oracle Cloud Infrastructure through GitHub Actions.

- Live API: [https://ledger.env.pm](https://ledger.env.pm/api/v1)
- Health check: [https://ledger.env.pm/api/v1/health](https://ledger.env.pm/api/v1/health)
- API documentation: [https://ledger.env.pm/docs](https://ledger.env.pm/docs)
- OpenAPI spec: [https://ledger.env.pm/openapi.json](https://ledger.env.pm/openapi.json)

Billing capabilities continue to be delivered incrementally through reviewed pull requests.

## Stack

- PHP 8.3 and Laravel 13
- Eloquent ORM
- PostgreSQL 17 in development and production
- SQLite for fast foundation tests
- Docker and Docker Compose
- Oracle Cloud Infrastructure (ARM64)
- Caddy reverse proxy and automatic HTTPS
- PHPUnit and Laravel Pint
- GitHub Actions CI/CD

## Quick start

Requirements: PHP 8.3+, Composer 2, and PostgreSQL 16+.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

The API runs at `http://localhost:8000`. Verify it with:

```bash
curl http://localhost:8000/api/v1/health
```

## Docker quick start

Docker Compose provides the API and PostgreSQL 17. Set a local database password before starting the services:

```bash
cp .env.example .env
# Set DB_PASSWORD in .env
docker compose up --build --wait
curl http://localhost:8080/api/v1/health
```

Stop the stack and retain its database with `docker compose down`, or remove the disposable database volume with `docker compose down --volumes`.
Authentication uses Laravel Sanctum bearer tokens. Register with `POST /api/v1/auth/register`, log in with `POST /api/v1/auth/login`, and send the returned token as `Authorization: Bearer <token>` to access `GET /api/v1/auth/me` and `POST /api/v1/auth/logout`.

Authenticated users can manage their own customer records through `GET|POST /api/v1/customers` and `GET|PATCH|DELETE /api/v1/customers/{id}`. Customer lists support `search`, `status`, `per_page`, `sort_by`, and `sort_dir` query parameters.

Products and services share the catalog endpoints at `GET|POST /api/v1/catalog-items` and `GET|PATCH|DELETE /api/v1/catalog-items/{id}`. Each item records its type, optional owner-scoped SKU, unit price, currency, and active status.

Draft invoices are available through `GET|POST /api/v1/invoices` and `GET|DELETE /api/v1/invoices/{id}`. Creation snapshots catalog descriptions and prices, calculates line and invoice totals in minor units, enforces a single currency, and requires an active customer and active catalog items owned by the authenticated user.

Transition invoices with `POST /api/v1/invoices/{id}/transitions` and inspect their ordered audit trail with `GET /api/v1/invoices/{id}/history`. The MVP lifecycle permits `draft → issued`, `draft → void`, and `issued → void`; payment workflows will own partial and paid states.

Authentication attempts are limited per normalized email and IP address, while authenticated API traffic is limited per user. Configure the per-minute budgets with `LEDGER_AUTH_RATE_LIMIT_PER_MINUTE` and `LEDGER_API_RATE_LIMIT_PER_MINUTE`; the health endpoint remains unthrottled for infrastructure probes.

All responses include `nosniff`, anti-framing, no-referrer, and restrictive browser-feature headers. HSTS remains at Caddy because the shared edge terminates HTTPS.

## Response contract

Every API response uses the same top-level fields:

```json
{
  "success": true,
  "code": "HEALTH_OK",
  "message": "EAV Ledger API is healthy",
  "data": {},
  "page": null,
  "sort": null,
  "filters": null,
  "error": null
}
```

## Production deployment

Production runs on an Oracle Cloud Infrastructure ARM64 VM using an isolated Docker Compose project:

```text
GitHub main
   ↓
GitHub Actions
   ↓ SSH
OCI Ubuntu VM
   ↓
Docker Compose (eav-ledger)
   ├── Laravel API
   └── PostgreSQL 17
   ↓
Caddy
   ↓
https://ledger.env.pm
```

The API binds only to `127.0.0.1:8081` on the host and Caddy exposes it over HTTPS. The existing container entrypoint applies Laravel migrations automatically when `RUN_MIGRATIONS=true`.

Interactive Swagger UI documentation is available at [https://ledger.env.pm/docs](https://ledger.env.pm/docs), backed by the versioned OpenAPI specification at [https://ledger.env.pm/openapi.json](https://ledger.env.pm/openapi.json).

## Verification

```bash
composer quality
```

This runs Laravel Pint in check mode followed by the test suite. Production deploys also verify the local and public health endpoints.

## MVP scope

The planned MVP covers authentication, customers, products and services, invoices, controlled invoice lifecycle transitions, payments, receipts, PDF generation, queued email delivery, authorization, Docker, and deployment readiness.

See [architecture notes](docs/architecture.md), [deployment notes](docs/deployment.md), the [roadmap](docs/roadmap.md), and the [project brief](docs/project-brief.md).
