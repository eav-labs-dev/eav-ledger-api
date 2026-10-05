# Deployment

Production runs on an Oracle Cloud Infrastructure ARM64 VM using Docker Compose and Caddy.

## Production topology

```text
ledger.env.pm
      ↓
    Caddy
      ↓
127.0.0.1:8081
      ↓
EAV Ledger API
      ↓
PostgreSQL 17
```

The production Compose project name is `eav-ledger`. PostgreSQL uses the external Docker volume `eav_ledger_postgres`, so application deploys do not own or remove the database volume.

## Required configuration

The production environment file is stored on the OCI host at:

```text
/opt/eav/ledger/app/.env.production
```

Required values:

- `APP_KEY`: persistent Laravel application key
- `APP_VERSION`: deployed application version
- `DB_DATABASE=eav_ledger`
- `DB_USERNAME=eav_user`
- `DB_PASSWORD`: strong production database password
- `LEDGER_AUTH_RATE_LIMIT_PER_MINUTE`: authentication attempts allowed per normalized email and IP (default `10`)
- `LEDGER_API_RATE_LIMIT_PER_MINUTE`: authenticated requests allowed per user (default `120`)

The environment file must not be committed. Use `.env.production.example` as the reference.

## Deployment flow

Merges to `main` run CI. After CI succeeds, the `Deploy to OCI` workflow:

1. connects to the OCI VM using the organization-level deployment secrets;
2. resets the server checkout to `origin/main`;
3. rebuilds and starts the isolated `eav-ledger` Compose project;
4. waits for `http://127.0.0.1:8081/api/v1/health`;
5. verifies `https://ledger.env.pm/api/v1/health`.

The API container runs as the unprivileged `www-data` user.

Application rate limits provide a predictable baseline and return the stable `RATE_LIMIT_EXCEEDED` API envelope with HTTP 429. Keep equivalent or stricter connection, request-size, and timeout controls at Caddy or the cloud edge because application throttling does not replace perimeter protection.

The application emits `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy` on every response. Configure HSTS at Caddy, where HTTPS terminates, so direct container health checks remain valid over loopback HTTP.
After each OCI deployment, the workflow checks the public health endpoint and fails unless all four defensive headers survive the complete HTTPS/Caddy path.

## Migrations

The existing Docker entrypoint runs:

```bash
php artisan migrate --force
```

when `RUN_MIGRATIONS=true`.

For this single-instance portfolio deployment, migrations run during application startup. In a horizontally scaled production system, run migrations as a controlled release step before shifting traffic.

## Health and shutdown

- Public health URL: `https://ledger.env.pm/api/v1/health`
- Swagger UI: `https://ledger.env.pm/docs`
- OpenAPI specification: `https://ledger.env.pm/openapi.json`
- Internal application port: `8080`
- Host binding: `127.0.0.1:8081`
- Stop grace period: 15 seconds

## Rollback

Redeploy a previous known-good commit or release and restore a compatible PostgreSQL backup when required. Database changes should remain backward compatible across adjacent releases whenever practical.
