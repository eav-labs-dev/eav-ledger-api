# Deployment

The production image is built from `Dockerfile` and runs the Laravel API as the unprivileged `www-data` user on port `8080`. PostgreSQL is external state and must not be embedded in the application container.

## Required configuration

- `APP_ENV=production`
- `APP_DEBUG=false`
- a persistent, securely generated `APP_KEY`
- `APP_URL` set to the public HTTPS origin
- PostgreSQL `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`
- `LOG_CHANNEL=stderr` or another platform-supported log sink

The Compose setup generates an ephemeral application key only when `APP_KEY` is empty. That fallback is for local review and CI, not production.

## Migrations

Set `RUN_MIGRATIONS=true` for a single release task or one controlled application instance. In horizontally scaled environments, run `php artisan migrate --force` once before shifting traffic and keep `RUN_MIGRATIONS=false` on normal replicas.

## Health and shutdown

- Liveness/readiness URL: `/api/v1/health`
- Container port: `8080`
- Stop grace period: at least 15 seconds

## Rollback

Redeploy the previous immutable image and restore the most recent compatible database backup if a migration is not backward compatible. Schema changes should remain additive until the following release whenever practical.
