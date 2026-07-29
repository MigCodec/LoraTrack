# System Architecture

## Purpose

This UML component diagram presents the complete logical architecture visible to a customer planning, integrating, securing, and operating LoraTrack. It shows responsibilities and information flows without exposing source-code structure or development tooling.

LoraTrack is a modular monolith: all modules shown inside the system boundary form one deployable application. The internal boundaries separate responsibilities and provider contracts; they do not represent independently deployed microservices.

![LoraTrack UML component diagram](architecture/diagrams/system-component-diagram.svg)

## Architectural Responsibilities

| Area | Responsibility |
| --- | --- |
| User access | Provides authenticated browser access for business users and organization administrators. |
| Identity and access | Authenticates local or Microsoft identities and enforces organization membership and role authorization. |
| Asset operations | Manages products, physical assets, tracking devices, assignments, locations, maps, and zones. |
| Integration management | Configures and supervises telemetry and catalog connectors without exposing stored credentials. |
| Telemetry intake | Validates and durably accepts provider events before deferred processing. |
| Scheduled processing | Normalizes, deduplicates, creates observations, calculates positions, evaluates alerts, synchronizes catalogs, and applies retention policies. |
| Operational reporting | Presents current state, history, maps, alerts, and connector health from normalized application data. |
| Persistence | Stores tenant-isolated operational records, private files, audit evidence, and durable ingestion buffers. |

## Integration Boundaries

- Users communicate with LoraTrack exclusively through HTTPS.
- Telemetry providers send authenticated HTTPS webhooks, or use an explicitly configured outbound MQTT connection.
- Catalog providers are accessed through authenticated HTTPS APIs.
- Microsoft Entra ID is optional and used only when Microsoft sign-in is enabled.
- SMTP is optional and supports invitations and notifications.
- External provider formats are translated at the integration boundary before entering operational records.

The editable UML source is provided in [`system-component-diagram.puml`](architecture/diagrams/system-component-diagram.puml).

## Engineering Assumptions

- The diagram is a logical component view of one modular-monolith deployment and does not prescribe separate servers or processes for each component.
- Provider payloads terminate at integration boundaries and are normalized before use by operational components.
- The durable ingestion inbox and operational repositories share the approved relational database unless a future architecture decision explicitly changes that boundary.
- Tenant authorization applies to every application service and persistence operation, even where repeated authorization dependencies are omitted for readability.
