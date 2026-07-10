# Compliance Baseline and Benchmarks

## Objective

Infrastructure decisions must not remain informal or open-ended. For enterprise LoraTrack deployments, database, server, operations, and evidence requirements should align with a concrete compliance baseline.

## Recommended Baseline

| Area | Reference | Usage in LoraTrack |
| --- | --- | --- |
| Information security | ISO/IEC 27001:2022 | ISMS governance, risk management, controls, continual improvement, and security evidence. |
| Security controls | ISO/IEC 27002:2022 | Guidance for access control, cryptography, operations, incidents, suppliers, and information protection. |
| IT service management | ISO/IEC 20000-1:2018 | Service operation, change, incident management, continuity, and service levels. |
| Business continuity | ISO 22301 | RTO/RPO, impact analysis, continuity, and recovery. |
| SQL Server hardening | CIS Microsoft SQL Server Benchmark | Verifiable secure configuration for SQL Server instances and databases. |
| Platform lifecycle | Microsoft Lifecycle | Supported versions, end of support, patching, and upgrade planning. |

## Mandatory SQL Server Criteria

SQL Server selection must be defined by this baseline:

1. The SQL Server version must be within Microsoft Lifecycle support.
2. The edition must support availability, backup, audit, and growth requirements.
3. The instance must be hardened against the CIS Benchmark for its version.
4. Operation must produce evidence compatible with ISO/IEC 27001 and ISO/IEC 27002.
5. Backup, restore, and continuity must be documented against ISO 22301.
6. Incidents, changes, and recurring operations must be governed against ISO/IEC 20000-1.

## Version Standard

For production:

- Conservative baseline: SQL Server 2022 Standard, latest approved Cumulative Update.
- Controlled new adoption: SQL Server 2025 Standard, only after staging validates migrations, PHP drivers, jobs, load, and CIS hardening.
- Versions older than SQL Server 2022 are not recommended for new deployments.

For development and QA:

- SQL Server Developer using the same major version as production.

For laboratory use:

- SQL Server Express only with explicit acceptance of its limits.

## Minimum Evidence

- Exact SQL Server version and CU.
- Microsoft ODBC Driver version.
- Microsoft Drivers for PHP for SQL Server version.
- `php -m` output showing `pdo_sqlsrv` and `sqlsrv`.
- `.env` configuration without secrets.
- Migration execution evidence.
- Backup and restore evidence.
- SQL Server user and role matrix.
- CIS hardening checklist or scan result.
- Infrastructure change record.
- Approved RTO/RPO.
- Credential rotation procedure.

## Sources

- ISO/IEC 27001:2022: https://www.iso.org/standard/27001
- ISO/IEC 27002:2022: https://www.iso.org/standard/75652.html
- ISO/IEC 20000-1:2018: https://www.iso.org/standard/70636.html
- ISO 22301: https://www.iso.org/standard/75106.html
- CIS Microsoft SQL Server Benchmark: https://www.cisecurity.org/benchmark/microsoft_sql_server
- Microsoft SQL Server downloads: https://www.microsoft.com/en-us/sql-server/sql-server-downloads
- Microsoft SQL Server Lifecycle: https://learn.microsoft.com/en-us/lifecycle/products/?terms=SQL%20Server
- Microsoft Drivers for PHP for SQL Server: https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server

## Documentation Limit

This baseline guides technical configuration and evidence. It does not state that LoraTrack, the supplier, or the customer environment is certified under ISO/IEC 27001, ISO/IEC 20000-1, or ISO 22301. Certification requires formal scope, organizational controls, audit, and approval by the corresponding certification body.
