# Ubuntu LTS Deployment Tutorial

## Scope

Tutorial for deploying LoraTrack on Ubuntu Server LTS with Nginx, PHP-FPM, MySQL or MariaDB, and cron.

Production baseline:

- PHP 8.2 or later.
- MySQL 8.0 or later, or MariaDB 10.6 or later.
- TLS for public access and database transport.

## 1. Prepare the Server

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y software-properties-common unzip git curl ca-certificates gnupg
```

## 2. Install PHP and Extensions

```bash
sudo apt install -y php php-fpm php-cli php-common php-mbstring php-xml php-curl php-zip php-bcmath php-gd php-intl php-opcache php-mysql
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

## 4. Configure MySQL or MariaDB Connectivity

Provision a supported MySQL or MariaDB service with encrypted transport. Install the customer-approved CA certificate on the application server and restrict database access to the application host.

Validate:

```bash
php -m | grep -E "mysqli|pdo_mysql"
sudo systemctl restart php*-fpm
```

Database account example:

```sql
CREATE DATABASE loratrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'loratrack_app'@'APPLICATION_HOST' IDENTIFIED BY 'CHANGE_ME_LONG_SECRET';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
    ON loratrack.* TO 'loratrack_app'@'APPLICATION_HOST';
FLUSH PRIVILEGES;
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

DB_CONNECTION=mysql
DB_HOST=mysql.example.com
DB_PORT=3306
DB_DATABASE=loratrack
DB_USERNAME=loratrack_app
DB_PASSWORD=CHANGE_ME_LONG_SECRET

MYSQL_ATTR_SSL_CA=/etc/ssl/certs/customer-mysql-ca.pem
MYSQL_ATTR_MAX_BUFFER_SIZE=6291456
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

## 11. Scheduler

Scheduler cron:

```cron
* * * * * cd /var/www/loratrack/current && php artisan schedule:run >> storage/logs/schedule.log 2>&1
```

No Laravel Queue systemd service is required.

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
sudo -u loratrack php artisan schedule:run
```

Validate login, dashboard, `/operations/health`, connector creation, TTI ingestion, private floor plan access, and scheduled processing.
