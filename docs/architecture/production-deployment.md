# Production Deployment Architecture

## Purpose

This UML deployment diagram defines the production infrastructure boundary, deployed application nodes, required services, trust zones, and principal communication paths. The final topology may use customer-managed servers or equivalent managed services while preserving the same security boundaries.

The application host runs one LoraTrack modular-monolith deployment. The web runtime and minute scheduler are two execution entry points into the same application release, configuration, domain modules, and database; they are not separate application services.

![LoraTrack UML production deployment diagram](architecture/diagrams/production-deployment-diagram.svg)

## Production Nodes

| Node | Required Responsibility |
| --- | --- |
| Edge security | Terminates trusted HTTPS, applies request limits, and optionally provides WAF or reverse-proxy controls. |
| Application host | Runs the web application and the minute scheduler with access to protected configuration and private storage. |
| Database service | Provides MySQL 8+, MariaDB 10.6+, or Microsoft SQL Server 2022+ persistence over encrypted transport and accepts connections only from approved application hosts. |
| Private storage | Retains customer floor plans and protected application files outside the public web root. |
| Monitoring service | Receives availability, application, scheduler, capacity, and security signals. |
| Backup repository | Stores encrypted, access-controlled database and private-file backups outside the application host. |

## Trust and Network Boundaries

- Only HTTPS is exposed to users and inbound webhook providers.
- The database is placed in a restricted data zone and is not publicly accessible.
- Administrative access uses the customer's approved VPN, bastion, or management network.
- Outbound traffic is restricted to configured identity, catalog, telemetry, email, DNS, NTP, monitoring, and backup destinations.
- Database and backup traffic must be encrypted in transit; backup copies must also be encrypted at rest. The selected database driver and trust configuration must match MySQL/MariaDB or SQL Server.
- Production secrets remain outside the public document root and are readable only by the application service account and authorized administrators.

The editable UML source is provided in [`production-deployment-diagram.puml`](architecture/diagrams/production-deployment-diagram.puml).

## Engineering Assumptions

- The baseline shows one logical application host; approved high-availability deployments may use multiple equivalent hosts with shared state and verified scheduler locking.
- The database and backup services may be managed services or dedicated customer infrastructure provided the documented trust boundaries and transport controls are preserved.
- Firewall rules must be derived from enabled connectors; optional SMTP and MQTT paths are not opened when those capabilities are disabled.
- Monitoring and backup paths are mandatory production responsibilities even when implemented by customer-standard platforms not named in the diagram.
