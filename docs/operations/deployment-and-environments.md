# Production Deployment and Configuration

## Base Requirements

- PHP 8.2 or higher.
- Composer.
- MySQL 8.0+, MariaDB 10.6+, or Microsoft SQL Server 2022+ with encrypted transport.
- PHP extensions required by Laravel and the selected database.
- Cron or Windows Task Scheduler for the minute scheduler invocation.
- TLS on the public domain.

## Environment Variables

Start from `.env.example`. Do not generate `.env` from a pipeline containing embedded secrets.

Critical variables:

- `APP_ENV`
- `APP_KEY`
- `APP_DEBUG=false` in production
- `APP_URL`
- `DB_*`
- `MYSQL_ATTR_SSL_CA` for MySQL/MariaDB deployments that require TLS.
- `MYSQL_ATTR_MAX_BUFFER_SIZE=6291456` when PDO uses `libmysql`, so Meraki payloads up to the 5 MiB HTTP limit are not truncated while being read.
- SQL Server encryption and certificate-trust parameters when `DB_CONNECTION=sqlsrv` is selected.
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

Run `php artisan schedule:run` once every minute. The scheduler drains durable webhook inboxes and processes observations, TTI/MQTT events, catalog synchronization requests, alerts, and retention activities. A persistent Laravel Queue worker is not required. Configure exactly one effective scheduler invocation per environment unless a tested distributed locking design has been approved.

The production scheduler account requires access to the application directory, PHP CLI, application configuration, logs, private storage, and the production database. Monitor invocation failures and total processing duration.

## Customer Validation Environment

| Environment | Purpose | Data |
| --- | --- | --- |
| pre-production | deployment, integration, capacity, recovery, and customer acceptance validation | synthetic, anonymized, or explicitly approved data |
| production | live operation | controlled data |

Do not use real payloads or real floor plans in non-production without approval and controls.

## Backup and Restore

The plan must cover:

- database;
- `storage/app/private`;
- production `.env`;
- logs required by audit;
- deployed release version.

Initial frequency recommendation:

- daily full backup;
- retention based on contract;
- restore test quarterly or before critical releases.

## Minimum Monitoring

- HTTP availability;
- 5xx errors;
- failed scheduled commands;
- pending webhook and connector backlog;
- database growth;
- disk space;
- inactive connectors;
- telemetry pending beyond threshold;
- TLS certificates;
- token and credential expiration.

## Rollback

Define before each change:

- previous release version;
- migration compatibility;
- pre-deploy backup;
- scheduler suspension and controlled resumption procedure;
- failed-event replay procedure;
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
