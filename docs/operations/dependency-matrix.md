# Dependency Matrix

This matrix reflects the dependencies observed in `composer.json` and the installed direct package versions reported by `composer show --direct`.

## Runtime

| Component | Required or Recommended Version | Notes |
| --- | --- | --- |
| PHP | 8.2 or higher | Laravel 12 requires a modern PHP runtime. PHP 8.3/8.4 may be used if extensions are available and validated. |
| Microsoft SQL Server | 2022 Standard or 2025 Standard | Recommended for Windows/IIS enterprise deployments under the ISO/CIS baseline. Requires `pdo_sqlsrv` and Microsoft ODBC Driver for SQL Server. |
| MariaDB | 10.6 or higher | Technical alternative supported by Laravel and used as the original project reference. |
| MySQL | 8.0 or higher | Technical alternative supported by Laravel. |
| Composer | 2.x | PHP dependency manager. |
| Linux web server | Nginx or Apache | The Ubuntu tutorial uses Nginx + PHP-FPM. |
| Windows web server | IIS 10 | Requires FastCGI and URL Rewrite. |
| Queue | Laravel Queue | Database queue or another configured driver. |
| Scheduler | cron, systemd timer, Task Scheduler, or supervisor | Required for `schedule:run` and workers. |

## Direct PHP Packages

| Package | Installed Version | Usage |
| --- | --- | --- |
| `laravel/framework` | 12.62.0 | Main framework. |
| `laravel/socialite` | 5.28.0 | OAuth/OIDC login. |
| `socialiteproviders/microsoft` | 4.9.0 | Microsoft provider. |
| `laravel/tinker` | 2.11.1 | Interactive console. |
| `php-mqtt/client` | 2.3.0 | MQTT listener. |
| `laravel/pint` | 1.29.3 | PHP code formatting. |
| `phpunit/phpunit` | 11.5.55 | Automated tests. |

## PHP Extensions

Install and enable:

- `bcmath`
- `ctype`
- `curl`
- `dom`
- `fileinfo`
- `gd`
- `json`
- `mbstring`
- `openssl`
- `pdo`
- `pdo_mysql` when using MySQL/MariaDB
- `pdo_sqlsrv` when using SQL Server
- `sqlsrv` recommended for diagnostics and non-PDO tooling
- `tokenizer`
- `xml`
- `xmlreader`
- `xmlwriter`
- `zip`

Recommended:

- `opcache`
- `intl`
- `redis` if Redis is used for cache, queues, or sessions.

## Optional External Services

| Service | Usage |
| --- | --- |
| Microsoft SQL Server | Recommended database for Windows/IIS deployments under the ISO/CIS baseline. |
| The Things Industries | LoRaWAN webhook source. |
| MQTT broker | Generic telemetry or TTI through MQTT. |
| Meraki | Location API. |
| SAP S/4HANA | Product Master catalog. |
| Microsoft Entra ID | Microsoft login. |
| SMTP | Email alerts and invitations. |

## Relevant Configuration Files

- `.env`
- `config/app.php`
- `config/database.php`
- `config/queue.php`
- `config/mail.php`
- `config/filesystems.php`
- `routes/api.php`
- `routes/console.php`
