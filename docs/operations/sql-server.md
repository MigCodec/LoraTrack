# Recommended SQL Server Baseline

## Recommendation

For regulated or high-criticality industrial customers, the database recommendation is fixed against the [compliance baseline](compliance-baseline.md):

- **Conservative production:** Microsoft SQL Server 2022 Standard, updated to the latest approved Cumulative Update.
- **Controlled new production adoption:** Microsoft SQL Server 2025 Standard, only after staging validates migrations, PHP drivers, CIS hardening, and expected LoraTrack load.
- **Development:** SQL Server Developer using the same major version as production.
- **Small pilots or labs:** SQL Server Express only if its limits are explicitly accepted.

SQL Server 2022 is the conservative recommendation because it has operational maturity and active support. SQL Server 2025 may be preferable when maximizing support lifetime, but it requires compatibility evidence before production use.

## Normalized Version and Edition

| Scenario | Version | Edition |
| --- | --- | --- |
| Stable enterprise production | SQL Server 2022 | Standard or Enterprise depending on HA/DR and licensing |
| Controlled new adoption | SQL Server 2025 | Standard or Enterprise |
| Development and QA | Same major as production | Developer |
| Demo or small pilot | SQL Server 2022/2025 | Express, if limits are acceptable |

Versions older than SQL Server 2022 are not recommended for new deployments.

## PHP/Laravel Drivers

LoraTrack can connect to SQL Server through Laravel's `sqlsrv` connection.

Requirements:

- Microsoft ODBC Driver for SQL Server.
- Microsoft Drivers for PHP for SQL Server.
- PHP extensions:
  - `pdo_sqlsrv`
  - `sqlsrv`

The latest GA driver version observed during documentation was 5.13.1.

## `.env` Configuration

```dotenv
DB_CONNECTION=sqlsrv
DB_HOST=sqlserver.example.com
DB_PORT=1433
DB_DATABASE=loratrack
DB_USERNAME=loratrack_app
DB_PASSWORD=CHANGE_ME_LONG_SECRET
```

For named instances, validate host and port format with the driver and network policy. Production should prefer an explicit port.

## Database and User Creation

Initial example:

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

Notes:

- `db_ddladmin` supports Laravel migrations.
- In mature production, use a separate migration account and remove DDL privileges from the runtime account.
- Do not use `sa` for the application.
- Align credential custody and rotation with ISO/IEC 27001 and ISO/IEC 27002.

## Collation and Unicode

LoraTrack stores names, descriptions, payloads, and metadata. Recommendations:

- Define collation in the project data design.
- Validate Unicode, accents, identifiers, and search behavior.
- Keep database, table, and column behavior consistent.

## Backups

Minimum recommendation:

- daily full backup;
- differential or incremental backup based on RPO;
- transaction log backups if recovery model is Full;
- periodic restore testing;
- encrypted backups;
- contract-defined retention.

## High Availability

For critical production, evaluate:

- SQL Server Always On Availability Groups;
- managed backup tooling;
- CPU, memory, IO, latency, and lock monitoring;
- separate data, log, and tempdb storage;
- index and statistics maintenance.

## Initial Sizing

Sizing depends on:

- number of assets;
- uplink frequency;
- observations per uplink;
- raw payload retention;
- historical position volume;
- concurrent users.

Pilot starting point:

- 4 vCPU;
- 16 GB RAM;
- SSD;
- monitored storage for data, logs, and backups;
- weekly growth review.

Production sizing must be based on load testing and agreed retention.

## Security

Recommendations:

- TLS for app-to-database connections when crossing networks.
- SQL Server not exposed publicly.
- Firewall limited to application servers.
- Least-privilege application account.
- Security and operational audit aligned with ISO/IEC 27001, ISO/IEC 27002, and ISO/IEC 20000-1.
- Encrypted backups.
- Transparent Data Encryption if required by policy.
- Do not log full connection strings.

## Laravel Compatibility

Before certifying SQL Server as production database, run in staging:

```bash
php artisan migrate:fresh --seed
php artisan test
```

Then validate manually:

- login;
- organizations;
- connectors;
- TTI ingestion;
- queue jobs;
- maps;
- asset track;
- floor plan uploads;
- alerts;
- telemetry cleanup.

## Reviewed Sources and Benchmarks

- Microsoft SQL Server downloads.
- Microsoft Learn: SQL Server 2025 features and Developer editions.
- Microsoft Learn: PHP drivers for SQL Server, GA version 5.13.1.
- Microsoft Learn: SQL Server support lifecycle.
- Microsoft Learn: SQL Server 2022 requirements.
- ISO/IEC 27001:2022.
- ISO/IEC 27002:2022.
- ISO/IEC 20000-1:2018.
- ISO 22301.
- CIS Microsoft SQL Server Benchmark.
