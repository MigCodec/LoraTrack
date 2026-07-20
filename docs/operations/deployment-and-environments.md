# Deployment and Environments

## Base Requirements

- PHP 8.2 or higher.
- Composer.
- SQL Server 2022/2025, MariaDB 10.6+, or MySQL 8+ depending on deployment baseline.
- PHP extensions required by Laravel and the selected database.
- Cron, Task Scheduler, Supervisor, systemd, or equivalent process management.
- TLS on the public domain.

## Environment Variables

Start from `.env.example`. Do not generate `.env` from a pipeline containing embedded secrets.

Critical variables:

- `APP_ENV`
- `APP_KEY`
- `APP_DEBUG=false` in production
- `APP_URL`
- `DB_*`
- `QUEUE_CONNECTION`
- `CACHE_STORE`
- `SESSION_DRIVER`
- `MAIL_*`
- `MICROSOFT_*` when Microsoft Entra ID is used

## Initial Installation

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Do not regenerate `APP_KEY` in an existing production environment because it invalidates encrypted data and sessions.

## File Permissions

Laravel requires write access to:

- `storage/`
- `bootstrap/cache/`

Floor plans must remain in private storage:

```text
storage/app/private
```

Do not expose floor plans through a public symlink.

## Scheduler

Run every minute:

```bash
php artisan schedule:run
```

Scheduled tasks:

- `loratrack:evaluate-alerts` every ten minutes.
- `loratrack:manage-telemetry-storage` hourly.
- `loratrack:prune-meraki-history` hourly.

## Queue Worker

The Laravel scheduler must run every minute. It drains the durable Meraki webhook inbox through `loratrack:process-meraki-webhooks`; without `schedule:run`, Meraki batches remain pending and no telemetry events are created.

Persistent worker:

```bash
php artisan queue:work --tries=3 --timeout=300
```

Cron-friendly worker:

```bash
php artisan queue:work --stop-when-empty --sleep=1 --tries=3 --timeout=120 --max-time=55
```

Avoid overlapping cron workers when the database has low connection limits.

## Recommended Environments

| Environment | Purpose | Data |
| --- | --- | --- |
| local | development | synthetic data |
| test/ci | automated tests | ephemeral database |
| staging | customer validation | anonymized or approved data |
| production | live operation | controlled data |

Do not use real payloads or real floor plans in non-production without approval and controls.

## Backup and Restore

The plan must cover:

- database;
- `storage/app/private`;
- production `.env`;
- logs required by audit;
- deployed version or commit.

Initial frequency recommendation:

- daily full backup;
- retention based on contract;
- restore test quarterly or before critical releases.

## Minimum Monitoring

- HTTP availability;
- 5xx errors;
- failed jobs;
- queue depth;
- database growth;
- disk space;
- inactive connectors;
- telemetry pending beyond threshold;
- TLS certificates;
- token and credential expiration.

## Rollback

Define before each change:

- previous commit;
- migration compatibility;
- pre-deploy backup;
- worker pause procedure;
- job retry procedure;
- user communication plan.

## Production Hardening

- `APP_DEBUG=false`.
- Mandatory TLS.
- Database not publicly exposed.
- Least-privilege database users.
- Encrypted backups.
- Secret rotation.
- WAF or reverse proxy when applicable.
- Request size limits.
- Centralized logs.
- Disable test accounts.
