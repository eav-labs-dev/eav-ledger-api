# EAV Ledger API

Laravel billing API for customers, invoices, payments, receipts, and auditable business workflows.

## Status

The Laravel 13 API foundation is active. Billing capabilities are being delivered incrementally through reviewed pull requests.

## Stack

- PHP 8.3 and Laravel 13
- Eloquent ORM
- PostgreSQL in development and production
- SQLite for fast foundation tests
- PHPUnit and Laravel Pint
- GitHub Actions CI

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

Authentication uses Laravel Sanctum bearer tokens. Register with `POST /api/v1/auth/register`, log in with `POST /api/v1/auth/login`, and send the returned token as `Authorization: Bearer <token>` to access `GET /api/v1/auth/me` and `POST /api/v1/auth/logout`.

Authenticated users can manage their own customer records through `GET|POST /api/v1/customers` and `GET|PATCH|DELETE /api/v1/customers/{id}`. Customer lists support `search`, `status`, `per_page`, `sort_by`, and `sort_dir` query parameters.

Products and services share the catalog endpoints at `GET|POST /api/v1/catalog-items` and `GET|PATCH|DELETE /api/v1/catalog-items/{id}`. Each item records its type, optional owner-scoped SKU, unit price, currency, and active status.

Draft invoices are available through `GET|POST /api/v1/invoices` and `GET|DELETE /api/v1/invoices/{id}`. Creation snapshots catalog descriptions and prices, calculates line and invoice totals in minor units, enforces a single currency, and requires an active customer and active catalog items owned by the authenticated user.

Transition invoices with `POST /api/v1/invoices/{id}/transitions` and inspect their ordered audit trail with `GET /api/v1/invoices/{id}/history`. The MVP lifecycle permits `draft → issued`, `draft → void`, and `issued → void`; payment workflows will own partial and paid states.

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

## Verification

```bash
composer quality
```

This runs Laravel Pint in check mode followed by the test suite.

## MVP scope

The planned MVP covers authentication, customers, products and services, invoices, controlled invoice lifecycle transitions, payments, receipts, PDF generation, queued email delivery, authorization, Docker, and deployment readiness.

See [architecture notes](docs/architecture.md), the [roadmap](docs/roadmap.md), and the [project brief](docs/project-brief.md).
