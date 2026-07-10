# Ubuntu LTS Deployment Tutorial

## Scope

Tutorial for deploying LoraTrack on Ubuntu Server LTS with Nginx, PHP-FPM, Composer, cron, and a queue worker. The recommended Microsoft database backend is SQL Server 2022/2025 on a dedicated server, certified Linux host, Windows Server, or Azure SQL service under the ISO/CIS baseline.

Project versions:

- Laravel Framework 12.62.0.
- Required PHP: 8.2+.
- Recommended database: SQL Server 2022 Standard with the latest approved CU, or SQL Server 2025 Standard after staging validation.
- MQTT client: `php-mqtt/client` 2.3.0.
- Microsoft OAuth: `laravel/socialite` 5.28.0 and `socialiteproviders/microsoft` 4.9.0.

## 1. Prepare the Server

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y software-properties-common unzip git curl ca-certificates gnupg
```

## 2. Install PHP and Extensions

```bash
sudo apt install -y php php-fpm php-cli php-common php-mbstring php-xml php-curl php-zip php-bcmath php-gd php-intl php-opcache
```

Verify:

```bash
php -v
php -m | grep -E "bcmath|curl|fileinfo|gd|mbstring|openssl|xml|zip"
```

Suggested `php.ini` values:

```ini
memory_limit=256M
upload_max_filesize=25M
post_max_size=30M
max_execution_time=120
date.timezone=America/Santiago
opcache.enable=1
opcache.enable_cli=1
```

Restart FPM:

```bash
sudo systemctl restart php*-fpm
```

## 3. Install Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

## 4. Configure SQL Server Connectivity

Recommended enterprise baseline:

- SQL Server 2022 Standard with latest approved CU for conservative production.
- SQL Server 2025 Standard for controlled new adoption after staging validation.
- SQL Server Developer for development.
- SQL Server Express only for labs or small pilots with explicit limit acceptance.

Install Microsoft ODBC Driver for SQL Server and PHP `sqlsrv`/`pdo_sqlsrv` extensions following Microsoft's repository instructions for the Ubuntu version.

Validate:

```bash
php -m | grep -E "sqlsrv|pdo_sqlsrv"
sudo systemctl restart php*-fpm
```

Database account example:

```sql
create database loratrack;
go
create login loratrack_app with password = 'CHANGE_ME_LONG_SECRET';
go
use loratrack;
go
create user loratrack_app for login loratrack_app;
go
alter role db_datareader add member loratrack_app;
alter role db_datawriter add member loratrack_app;
alter role db_ddladmin add member loratrack_app;
go
```

After migrations, evaluate removing DDL permissions from the runtime account and using a separate migration account.

## 5. Install Composer

```bash
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

Validate the official checksum before running the installer in controlled environments.

## 6. Application Directory

```bash
sudo adduser --system --group --home /var/www/loratrack loratrack
sudo mkdir -p /var/www/loratrack/current
sudo chown -R loratrack:www-data /var/www/loratrack
sudo -u loratrack git clone REPO_URL /var/www/loratrack/current
cd /var/www/loratrack/current
```

Install dependencies:

```bash
sudo -u loratrack composer install --no-dev --optimize-autoloader
```

## 7. Configure `.env`

```bash
sudo -u loratrack cp .env.example .env
sudo -u loratrack nano .env
```

Minimum values:

```dotenv
APP_NAME=LoraTrack
APP_ENV=production
APP_DEBUG=false
APP_URL=https://loratrack.example.com
APP_TIMEZONE=America/Santiago

DB_CONNECTION=sqlsrv
DB_HOST=sqlserver.example.com
DB_PORT=1433
DB_DATABASE=loratrack
DB_USERNAME=loratrack_app
DB_PASSWORD=CHANGE_ME_LONG_SECRET

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

Generate `APP_KEY` only for a new installation:

```bash
sudo -u loratrack php artisan key:generate
```

## 8. Permissions, Migrations, and Cache

```bash
sudo chown -R loratrack:www-data /var/www/loratrack/current
sudo find /var/www/loratrack/current -type f -exec chmod 0644 {} \;
sudo find /var/www/loratrack/current -type d -exec chmod 0755 {} \;
sudo chmod -R ug+rwx /var/www/loratrack/current/storage /var/www/loratrack/current/bootstrap/cache

sudo -u loratrack php artisan migrate --force
sudo -u loratrack php artisan config:cache
sudo -u loratrack php artisan route:cache
sudo -u loratrack php artisan view:cache
```

## 9. Nginx Site

Use `/var/www/loratrack/current/public` as the web root.

```nginx
server {
    listen 80;
    server_name loratrack.example.com;
    root /var/www/loratrack/current/public;
    index index.php index.html;
    client_max_body_size 30M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Adjust the PHP-FPM socket to the installed version.

## 10. TLS

Use an approved PKI certificate or Let's Encrypt where allowed.

## 11. Scheduler and Queue

Scheduler cron:

```cron
* * * * * cd /var/www/loratrack/current && php artisan schedule:run >> storage/logs/schedule.log 2>&1
```

Recommended systemd queue worker:

```ini
[Unit]
Description=LoraTrack Laravel Queue Worker
After=network.target

[Service]
User=loratrack
Group=www-data
WorkingDirectory=/var/www/loratrack/current
ExecStart=/usr/bin/php artisan queue:work --tries=3 --timeout=300
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

## 12. Optional Microsoft Login

```dotenv
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_TENANT_ID=
MICROSOFT_REDIRECT_URI=https://loratrack.example.com/auth/microsoft/callback
```

Then run:

```bash
sudo -u loratrack php artisan config:cache
```

## 13. Post-Deployment Verification

```bash
curl -I https://loratrack.example.com/login
sudo -u loratrack php artisan about
sudo -u loratrack php artisan queue:work --stop-when-empty -v
sudo -u loratrack php artisan schedule:run
```

Validate login, dashboard, `/operations/health`, connector creation, TTI ingestion, private floor plan access, and queue processing.
