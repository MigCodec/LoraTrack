# Windows Server and IIS Deployment Tutorial

## Scope

Tutorial for deploying LoraTrack on Windows Server with IIS, PHP FastCGI, Composer, Microsoft SQL Server, and Task Scheduler.

Project versions:

- Laravel Framework 12.62.0.
- Required PHP: 8.2+.
- Recommended database: Microsoft SQL Server 2022 Standard with latest approved CU, or SQL Server 2025 Standard after ISO/CIS staging validation.
- Microsoft OAuth: `laravel/socialite` 5.28.0 and `socialiteproviders/microsoft` 4.9.0.
- MQTT client: `php-mqtt/client` 2.3.0.

## 1. Required Components

Install:

- Windows Server 2019/2022/2025.
- IIS 10.
- PHP 8.2+ Non Thread Safe x64.
- Visual C++ Redistributable required by PHP.
- Composer 2.x for Windows.
- Microsoft SQL Server 2022/2025.
- Microsoft ODBC Driver for SQL Server.
- Microsoft Drivers for PHP for SQL Server: `pdo_sqlsrv` and `sqlsrv`.
- IIS URL Rewrite Module.
- Git for Windows or an approved deployment mechanism.

## 2. Enable IIS and CGI

PowerShell as Administrator:

```powershell
Install-WindowsFeature Web-Server, Web-CGI, Web-Common-Http, Web-Default-Doc, Web-Static-Content, Web-Http-Errors, Web-Http-Redirect, Web-Filtering, Web-Mgmt-Console
```

Install URL Rewrite from an approved internal package or Microsoft source.

## 3. Install PHP

Install PHP NTS x64, for example:

```text
C:\PHP\8.3
```

Copy `php.ini-production` to `php.ini` and enable:

```ini
extension_dir="ext"
extension=bcmath
extension=curl
extension=fileinfo
extension=gd
extension=mbstring
extension=openssl
extension=pdo_sqlsrv
extension=sqlsrv
extension=zip

cgi.force_redirect=0
cgi.fix_pathinfo=1
fastcgi.impersonate=1

memory_limit=256M
upload_max_filesize=25M
post_max_size=30M
max_execution_time=120
date.timezone=America/Santiago
opcache.enable=1
opcache.enable_cli=1
```

Verify:

```powershell
php -v
php -m
```

## 4. Configure PHP FastCGI in IIS

Add a handler mapping:

- Request path: `*.php`
- Module: `FastCgiModule`
- Executable: `C:\PHP\8.3\php-cgi.exe`
- Name: `PHP via FastCGI`

## 5. SQL Server Setup

Recommendation:

- Conservative production: SQL Server 2022 Standard, latest approved CU.
- Controlled new adoption: SQL Server 2025 Standard, validated in staging.
- Development: SQL Server Developer.
- Lab: SQL Server Express only if limits are accepted.

Create database and login:

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

Validate:

```sql
select @@version;
```

## 6. Application Directory

```powershell
New-Item -ItemType Directory -Force C:\inetpub\loratrack
git clone REPO_URL C:\inetpub\loratrack
cd C:\inetpub\loratrack
composer install --no-dev --optimize-autoloader
```

## 7. Configure `.env`

```powershell
Copy-Item .env.example .env
notepad .env
```

Minimum values:

```dotenv
APP_NAME=LoraTrack
APP_ENV=production
APP_DEBUG=false
APP_URL=https://loratrack.example.com
APP_TIMEZONE=America/Santiago

DB_CONNECTION=sqlsrv
DB_HOST=127.0.0.1
DB_PORT=1433
DB_DATABASE=loratrack
DB_USERNAME=loratrack_app
DB_PASSWORD=CHANGE_ME_LONG_SECRET

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

Generate `APP_KEY` only for a new installation:

```powershell
php artisan key:generate
```

## 8. Configure IIS Site

The site physical path must be:

```text
C:\inetpub\loratrack\public
```

Example:

```powershell
Import-Module WebAdministration
New-Website -Name "LoraTrack" -Port 80 -HostHeader "loratrack.example.com" -PhysicalPath "C:\inetpub\loratrack\public"
```

Configure HTTPS using an approved PKI or public CA certificate.

## 9. `web.config`

Create `C:\inetpub\loratrack\public\web.config`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <rewrite>
      <rules>
        <rule name="Laravel" stopProcessing="true">
          <match url=".*" />
          <conditions logicalGrouping="MatchAll">
            <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
            <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
          </conditions>
          <action type="Rewrite" url="index.php" />
        </rule>
      </rules>
    </rewrite>
    <security>
      <requestFiltering>
        <requestLimits maxAllowedContentLength="31457280" />
      </requestFiltering>
    </security>
    <defaultDocument>
      <files>
        <clear />
        <add value="index.php" />
      </files>
    </defaultDocument>
  </system.webServer>
</configuration>
```

Do not point IIS to the project root.

## 10. NTFS Permissions

Grant read access to the project and write access only to:

- `storage`
- `bootstrap\cache`

Example:

```powershell
icacls C:\inetpub\loratrack /grant "IIS AppPool\LoraTrack:(OI)(CI)RX"
icacls C:\inetpub\loratrack\storage /grant "IIS AppPool\LoraTrack:(OI)(CI)M"
icacls C:\inetpub\loratrack\bootstrap\cache /grant "IIS AppPool\LoraTrack:(OI)(CI)M"
```

## 11. Migrate and Optimize

```powershell
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 12. Task Scheduler

Scheduler task:

- Program: `C:\PHP\8.3\php.exe`
- Arguments: `artisan schedule:run`
- Start in: `C:\inetpub\loratrack`
- Repeat every minute.

Queue task command:

```text
artisan queue:work --stop-when-empty --sleep=1 --tries=3 --timeout=120 --max-time=55
```

For production, prefer a Windows service wrapper such as WinSW, NSSM, or an approved service manager for persistent workers.

## 13. Optional Microsoft Login

```dotenv
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_TENANT_ID=
MICROSOFT_REDIRECT_URI=https://loratrack.example.com/auth/microsoft/callback
```

Apply:

```powershell
php artisan config:cache
iisreset
```

## 14. Post-Deployment Verification

```powershell
Invoke-WebRequest https://loratrack.example.com/login -UseBasicParsing
php artisan about
php artisan queue:work --stop-when-empty -v
php artisan schedule:run
```

Validate login, dashboard, `/operations/health`, floor plan access, connector creation, telemetry processing, and email delivery.

## 15. Troubleshooting

- Error 500: inspect `storage\logs\laravel.log` and Windows Event Viewer.
- Laravel routes return 404: verify URL Rewrite and `web.config`.
- Permission errors: reapply permissions on `storage` and `bootstrap\cache`.
- `.env` changes do not apply: run `php artisan config:clear`, `php artisan config:cache`, and `iisreset`.
- Queue does not process: run `php artisan queue:work --stop-when-empty -v` manually and inspect Task Scheduler history.
