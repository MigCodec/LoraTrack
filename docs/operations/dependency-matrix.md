# Production Infrastructure Requirements

This section defines the infrastructure a customer must provide to operate LoraTrack in production. Final capacity must be confirmed from the expected number of organizations, assets, users, connectors, telemetry volume, retention period, and availability target.

## Application Runtime

| Component | Production Requirement | Operational Notes |
| --- | --- | --- |
| Operating system | Supported Ubuntu LTS or Windows Server with IIS 10 | Apply vendor security updates under the customer's patch policy. |
| PHP | 8.2 or later, supported by the selected operating system | Enable OPcache and configure memory and request limits for the expected workload. |
| Web server | Nginx or Apache on Linux; IIS 10 on Windows | Publish only the Laravel `public` directory and enforce HTTPS. |
| Database | MySQL 8.0+, MariaDB 10.6+, or Microsoft SQL Server 2022+ | Use a dedicated database and least-privilege application account. Require encrypted transport and the matching PHP PDO driver. |
| Scheduler | Cron or Windows Task Scheduler | Execute `php artisan schedule:run` once per minute. A persistent Laravel Queue worker is not required. |
| Storage | Persistent application storage plus independent backup capacity | Private floor plans, generated data, and application logs must not be exposed by the web server. |
| DNS and TLS | Customer-controlled production hostname and valid trusted certificate | Monitor certificate expiration and redirect HTTP to HTTPS. |
| Time synchronization | NTP-synchronized application and database hosts | Required for reliable telemetry, audit, and troubleshooting timestamps. |

## Required PHP Extensions

Enable `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `json`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, and `zip`. Enable `pdo_mysql` for MySQL/MariaDB or Microsoft `pdo_sqlsrv` for SQL Server. `intl` is recommended. Redis support is optional when Redis is selected for cache or sessions.

## Database Transport and Capacity

- Require encrypted transport between the application and database and install the required trust chain on the application host.
- For MySQL/MariaDB, configure `MYSQL_ATTR_SSL_CA` with the absolute CA certificate path.
- When the PHP MySQL driver uses `libmysql`, configure `MYSQL_ATTR_MAX_BUFFER_SIZE=6291456` so accepted Meraki payloads are not truncated during database reads.
- For SQL Server, install Microsoft ODBC Driver 18 and `pdo_sqlsrv`, enable certificate validation, and use TCP 1433 or the customer-approved explicit port.
- Allocate database memory, connections, storage, and IOPS from measured production volume rather than generic minimums.
- Monitor table growth, slow queries, connection utilization, storage consumption, and backup duration.

## Network Connectivity

The customer firewall and proxy policy must permit only the flows required by enabled capabilities:

| Flow | Direction | Purpose |
| --- | --- | --- |
| HTTPS 443 | Inbound | User access and authenticated webhook delivery. |
| Database TLS | Application to database | TCP 3306 for MySQL/MariaDB or TCP 1433 for SQL Server by default; restrict by source and destination. |
| HTTPS 443 | Outbound | Microsoft identity and configured catalog or telemetry providers. |
| SMTP submission | Outbound | Notifications and invitations when email is enabled. |
| MQTT TLS | Outbound | Required only for configured MQTT connectors. |
| DNS and NTP | Outbound | Name resolution and time synchronization. |

The database must not be exposed to the public internet. Administrative access should use the customer's controlled management network, VPN, or bastion service.

## External Services by Enabled Capability

| Service | When Required |
| --- | --- |
| Microsoft Entra ID | Microsoft sign-in. |
| SMTP service | Invitations, password workflows, and operational notifications. |
| The Things Industries | TTI webhook or MQTT telemetry. |
| Meraki | Meraki Scanning/Location API ingestion. |
| SAP S/4HANA or another catalog provider | Automated catalog synchronization. |
| Central logging and monitoring platform | Production alerting, audit support, and incident investigation. |
| Backup repository | Encrypted database and private-file backups stored outside the application host. |

## Capacity Inputs Required Before Sizing

The customer and implementation team must agree on:

- expected concurrent and named users;
- organizations, sites, buildings, floors, assets, and devices;
- number and type of connectors;
- average and peak webhook or MQTT events per minute;
- maximum accepted payload size;
- observation, audit, and location-history retention periods;
- floor-plan file volume;
- required availability, RPO, and RTO;
- backup retention and restoration-test frequency.

Do not approve production capacity until representative telemetry and user workloads have been validated in a customer-approved pre-production environment.
