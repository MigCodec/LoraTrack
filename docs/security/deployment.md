# Secure GitHub and cPanel deployment

## Repository controls required before publication

Configure GitHub before the first push:

1. Protect `main`: require pull requests, at least one independent approval, resolution of review conversations and signed commits where organizational policy requires them. El despliegue directo actual no espera CI; activar checks obligatorios requiere volver a incorporar esa dependencia explícitamente.
2. Disallow force pushes and branch deletion. Restrict who can push and deploy.
3. Enable secret scanning, push protection, Dependabot alerts, dependency graph and private vulnerability reporting. Configura por separado un SAST compatible con PHP; el workflow CodeQL anterior fue retirado porque CodeQL no analiza PHP.
4. Create a `production` Environment with required reviewers, deployment branch restricted to `main`, and no administrator bypass.
5. Use organization SSO/MFA, least-privilege teams, periodic access reviews, and an emergency-access procedure.

## GitHub Environment configuration

Secrets:

- `SSH_HOST`: cPanel SSH hostname.
- `SSH_USER`: dedicated deployment user, not `root`.
- `SSH_PORT`: SSH port.
- `SSH_KEY`: private key dedicated to this repository and environment.
- `SSH_PASSPHRASE`: passphrase used to unlock `SSH_KEY` through an ephemeral `ssh-agent`.

Variables:

- `DEPLOY_PATH`: Laravel application directory; its `public/` subdirectory must be the web document root.
- `PHP_BIN`: optional absolute cPanel PHP 8.2 binary, for example `/opt/cpanel/ea-php82/root/usr/bin/php`.
- `COMPOSER_BIN`: optional absolute, preinstalled Composer 2 binary.

The server must already contain a protected `.env` with `APP_ENV=production`, `APP_DEBUG=false`, a persistent `APP_KEY`, database credentials, mail configuration, and Microsoft credentials where applicable. The workflow refuses to create or replace `.env` and refuses to download executable tooling at deployment time.

## Server prerequisites

- Restrict the deployment key in `~/.ssh/authorized_keys` and rotate it periodically.
- Keep PHP, Composer, MariaDB, OpenSSH, and cPanel patched.
- Point the domain only to `DEPLOY_PATH/public`; deny web access to `.env`, `.git`, `storage`, backups, and application source.
- Run HTTPS with HSTS at the reverse proxy after confirming every subdomain supports HTTPS.
- Configure off-host encrypted database and private-file backups, restoration tests, retention, and recovery objectives.
- Configure cron for `schedule:run` and bounded queue processing. Supervise persistent MQTT separately if hosting permits it.
- Centralize security logs with restricted access and retention aligned to the approved policy.

## Deployment behavior

The workflow connects directly to `DEPLOY_PATH`, initializes Git there on the first deployment and uses `git pull --ff-only origin main` thereafter. It accepts the SSH host key automatically on first connection, checks production settings, preserves ignored `.env` and `storage` data, runs migrations, rebuilds Laravel caches, and restarts queue workers. CI runs independently and does not currently block production deployment.

Database migrations are not automatically reversible. A tested backup and rollback decision are mandatory before changes that alter or delete production data.
