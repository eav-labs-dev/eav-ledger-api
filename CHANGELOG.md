# Changelog

## Unreleased

- Extended the published OpenAPI contract with catalog, invoice, transition, and audit-history endpoints.
- Add configurable authentication and authenticated API rate limits with stable HTTP 429 errors and defensive response headers.
- Added a non-root production image, PostgreSQL Compose environment, container smoke-test CI, and deployment notes.
- Added controlled invoice issue/void transitions with row locking and persistent actor audit history.
- Added owner-scoped draft invoice creation, line-item snapshots, deterministic totals, filters, pagination, and safe deletion rules.
- Added owner-scoped product and service catalog CRUD with pricing, filters, sorting, and pagination.
- Added authenticated customer CRUD with ownership isolation, filtering, sorting, and pagination.
- Added Sanctum token registration, login, current-user, and logout endpoints.
- Added the Laravel 13 API foundation, stable response envelope, health endpoint, baseline tests, and CI.

## 0.1.0 - Portfolio Rebuild Started

- Reset repository for EAV Labs portfolio rebuild.
- Added initial README and documentation structure.
