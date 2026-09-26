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
