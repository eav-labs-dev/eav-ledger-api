# Architecture

EAV Ledger API is a modular Laravel application. HTTP controllers validate and authorize requests, application services coordinate billing rules, Eloquent models persist aggregates, and API resources own response mapping.

## Initial boundaries

- **Identity:** users, authentication, and authorization.
- **Catalog:** billable products and services.
- **Customers:** customer records and billing details.
- **Invoicing:** invoice lines, totals, and lifecycle transitions.
- **Payments:** payment allocation, balances, and receipts.
- **Delivery:** PDF rendering and queued email abstractions.

The MVP remains a single deployable application and database. These boundaries organize the code without introducing unnecessary services.

## API contract

All responses use the stable envelope documented in the README. Domain errors expose safe codes and validation details without leaking framework or database internals.

## Persistence

PostgreSQL is the target runtime database. Migrations are the source of truth for schema changes. SQLite is used only for fast foundation tests; business workflow tests will run against PostgreSQL in CI.

## Invoice aggregate

An invoice owns immutable line snapshots of catalog descriptions, quantities, unit prices, tax rates, and calculated amounts. Draft creation runs in a database transaction, validates owner and currency boundaries, and calculates money in integer minor units before persisting decimal values. This prevents floating-point drift while retaining conventional database columns and API strings for money.

Lifecycle changes run under a database row lock and use an explicit transition map. Each accepted change appends an actor, previous state, next state, note, and timestamp to invoice history. Payment states remain reserved for the payment allocation boundary.

Payments are append-only records applied while holding the invoice row lock. The service calculates paid and outstanding amounts in integer minor units, rejects overpayment, and advances invoice state from `issued` to `partially_paid` or `paid`. Owner-scoped external references protect webhook and operator retries from duplicate allocation.
