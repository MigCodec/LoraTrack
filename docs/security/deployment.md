# Secure GitHub and cPanel Deployment

## Repository Controls Required Before Publication

Configure GitHub before the first production push:

1. Protect `main`: require pull requests, at least one independent approval, resolved review conversations, and signed commits where required by policy. The current direct deployment workflow does not wait for CI; mandatory checks must be reintroduced explicitly if required.
2. Disallow force pushes and branch deletion. Restrict who can push and deploy.
3. Enable secret scanning, push protection, Dependabot alerts, dependency graph, and private vulnerability reporting. Configure a PHP-compatible SAST tool separately; CodeQL does not currently analyze PHP in this repository.
4. Create a `production` Environment with required reviewers, deployment branch restricted to `main`, and no administrator bypass.
5. Use organization SSO/MFA, least-privilege teams, periodic access reviews, and an emergency-access procedure.

## GitHub Environment Configuration

Secrets:

- `SSH_HOST`: cPanel SSH hostname.
- `SSH_USER`: dedicated deployment user, not `root`.
- `SSH_PORT`: SSH port.
- `SSH_KEY`: private key dedicated to this repository and environment.
- `SSH_PASSPHRASE`: passphrase used to unlock only the runner's temporary copy of `SSH_KEY`; that copy is deleted after deployment.

Variables:

- `DEPLOY_PATH`: Laravel application directory. Its `public/` subdirectory must be the web document root.
- `PHP_BIN`: optional absolute cPanel PHP binary, for example `/opt/cpanel/ea-php84/root/usr/bin/php`.
- `COMPOSER_BIN`: optional absolute path to a preinstalled Composer 2 executable or `composer.phar`. PHAR files are run with `PHP_BIN`.

The server must already contain a protected `.env` with `APP_ENV=production`, `APP_DEBUG=false`, a persistent `APP_KEY`, database credentials, mail configuration, and Microsoft credentials where applicable. The workflow must not create or replace `.env` and must not download executable tooling during deployment.

## Server Prerequisites

- Restrict the deployment key in `~/.ssh/authorized_keys` and rotate it periodically.
- Keep PHP, Composer, SQL Server/MariaDB/MySQL, OpenSSH, and cPanel patched.
- Point the domain only to `DEPLOY_PATH/public`; deny web access to `.env`, `.git`, `storage`, backups, and application source.
- Run HTTPS with HSTS at the reverse proxy after confirming every subdomain supports HTTPS.
- Configure off-host encrypted database and private-file backups, restore tests, retention, and recovery objectives.
- Configure cron for `schedule:run` and bounded queue processing. Supervise MQTT separately if hosting permits it.
- Centralize security logs with restricted access and approved retention.

## Deployment Behavior

The workflow connects directly to `DEPLOY_PATH`, initializes Git there on first deployment, and uses `git pull --ff-only origin main` afterwards. It preserves ignored `.env` and `storage` data, runs migrations, and rebuilds Laravel caches. Background processing continues through the minute scheduler cron.

Database migrations are not automatically reversible. A tested backup and rollback decision are mandatory before changes that alter or delete production data.
