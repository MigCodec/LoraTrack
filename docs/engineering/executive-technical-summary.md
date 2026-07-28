# Executive Product and Technical Summary

## Executive Purpose

LoraTrack provides a governed inventory and location platform for physical assets in industrial indoor and outdoor environments. It combines product catalogs, asset identity, device assignments, IoT telemetry, floor plans, location estimates, alerts, and operational history in a single multi-organization application.

The platform is intended to improve asset visibility, reduce manual location searches, preserve chain-of-custody evidence, and provide a consistent operational record across catalog, telemetry, and location workflows.

## Business Outcomes

LoraTrack is designed to support the following outcomes:

- a consolidated register of products, physical assets, and tracking devices;
- reduced time spent locating assets and investigating missing equipment;
- traceable device-to-asset assignments with historical validity;
- earlier identification of offline assets, low-confidence locations, and zone events;
- reusable integration with enterprise catalogs and IoT providers;
- organization-level isolation for shared-platform operation;
- auditable operational changes and reproducible location evidence.

Realized benefits depend on device coverage, floor-plan quality, calibration, integration availability, user adoption, and agreed operating procedures.

## Product Scope

### Included

- Product and SKU catalog management and synchronization.
- Physical asset registration and time-bound device assignment.
- Device inventory for BLE beacons, scanners, gateways, LoRaWAN trackers, and Meraki access points.
- Sites, buildings, floors, zones, floor plans, and installed anchors.
- Telemetry ingestion from TTI, generic MQTT, and Cisco Meraki Location API.
- Payload normalization, deterministic deduplication, signal observations, and position estimates.
- Operational maps, asset history, alerts, health monitoring, and audit records.
- Role-based access and organization isolation.

### Excluded or Externally Managed

- LoRaWAN network-server and radio-network operation, which remain with TTI.
- Guaranteed physical accuracy without site calibration and adequate anchor geometry.
- Certified metrology, personnel safety, emergency response, or life-safety positioning.
- Customer governance controls such as service ownership, business continuity approval, and formal certification.

## Current Delivery Status

| Area | Current state | Executive interpretation |
| --- | --- | --- |
| Core inventory and assets | Implemented | Available for controlled operational use after environment validation. |
| Organization isolation and roles | Implemented and covered by automated tests | Requires customer access reviews and role ownership. |
| TTI, MQTT, and Meraki telemetry | Implemented | Requires provider configuration, credentials, monitoring, and capacity validation. |
| Catalog integrations | SAP, Business Central, Shopify, Odoo, and CSV adapters implemented | Provider permissions and supported API versions must be validated per deployment. |
| Positioning | RSSI-based estimation, calibration, confidence, and history implemented | Accuracy remains environment-dependent and must be field-validated. |
| Deployment and operations | Documented for Linux and Windows | Production readiness depends on backup, recovery, monitoring, TLS, and operational ownership. |
| Formal certification | Not claimed | Certification requires independently verified organizational and technical evidence. |

## Architecture and Operating Model

LoraTrack is delivered as a modular Laravel 12 monolith. This provides one controlled application deployment while preserving logical boundaries among catalog, assets, devices, locations, connectors, telemetry, positioning, dashboard, and identity capabilities.

The production operating model requires:

- PHP 8.2 or later and a supported MySQL, MariaDB, or SQL Server deployment baseline;
- HTTPS and protected application secrets;
- the Laravel scheduler invoked every minute;
- an MQTT listener only when MQTT connectors are enabled;
- database backup, tested restoration, monitoring, and incident procedures;
- named owners for application, infrastructure, integrations, security, and data.

Deferred telemetry and catalog processing is performed by scheduled Laravel commands. A persistent Laravel Queue worker is not required.

## Security and Governance Controls

- Business data is scoped by `organization_id` and active membership.
- Effective roles belong to organization memberships.
- Connector credentials are encrypted at rest and excluded from responses and logs.
- External events are authenticated, persisted, deduplicated, and processed idempotently.
- Floor plans and organization branding files use private storage.
- Server-side authorization protects every supported operation.
- Web mutations produce audit records and correlation identifiers.
- Connector errors are sanitized before presentation.
- Public documentation does not claim certification or independent assurance.

These application controls must be complemented by infrastructure hardening, access reviews, supplier management, incident response, backup governance, continuity planning, training, and evidence retention.

## Executive Risks and Treatments

| Risk | Business impact | Required treatment | Accountable role |
| --- | --- | --- | --- |
| Inadequate anchor coverage or calibration | Incorrect or unavailable asset positions | Conduct site survey, calibration, acceptance testing, and periodic revalidation. | Operations and engineering owner |
| Scheduler, connector, or provider interruption | Delayed telemetry and stale dashboard information | Monitor backlog and last activity; define alerting and recovery procedures. | Platform operations owner |
| Uncontrolled retention growth | Increased storage cost and degraded performance | Approve retention periods, capacity thresholds, archival, and deletion rules. | Data and service owner |
| Credential exposure or excessive access | Unauthorized data or integration access | Apply least privilege, rotation, access reviews, and protected secret storage. | Security and application owner |
| Untested backup or recovery | Extended outage or permanent data loss | Define RPO/RTO, automate backups, and test restoration on a schedule. | Infrastructure and continuity owner |
| Provider or API change | Integration failure or incomplete catalog/telemetry data | Pin supported contracts, monitor provider changes, and maintain contract tests. | Integration owner |
| Misrepresentation of positioning accuracy | Unsafe or incorrect operational decisions | Publish confidence and limitations; prohibit life-safety or certified-metrology claims. | Product and operational owner |

## Executive Metrics

Production approval should define targets and reporting ownership for at least:

- service availability and scheduled-processing success rate;
- webhook response latency and rejected-request rate;
- pending, failed, and oldest telemetry-event age;
- connector last-success time and synchronization failure rate;
- asset and device coverage, assignment completeness, and stale-device rate;
- percentage of estimates meeting the agreed confidence and accuracy threshold;
- alert delivery success and incident acknowledgment time;
- backup success, restoration-test result, achieved RPO, and achieved RTO;
- active-user adoption and completion of required operational workflows.

Numeric targets must be approved for each customer environment after baseline and field testing; this document does not invent universal SLA or accuracy commitments.

## Decisions Required Before Production Approval

Executive sponsors and service owners must approve:

1. Business scope, participating organizations, and accountable service owner.
2. Hosting model, support model, operating hours, and escalation path.
3. Target availability, response times, RPO, RTO, and retention periods.
4. Accepted positioning use cases, field accuracy criteria, and prohibited uses.
5. Integration ownership, provider credentials, API permissions, and supplier dependencies.
6. Role model, access-review frequency, and administrative segregation.
7. Monitoring, incident response, backup, restoration, and continuity evidence.
8. Pilot acceptance criteria, rollout stages, training, and change-management plan.
9. Budget and staffing for infrastructure, field commissioning, support, and lifecycle maintenance.

## Recommended Adoption Stages

1. **Technical validation:** verify deployment, security baseline, integrations, scheduler, backup, and restoration.
2. **Controlled pilot:** commission one representative site, calibrate anchors, establish baseline metrics, and obtain user feedback.
3. **Operational acceptance:** approve accuracy, monitoring, procedures, support, training, and risk treatment.
4. **Phased rollout:** expand by site or business unit with explicit acceptance checkpoints.
5. **Service operation:** review KPIs, incidents, access, capacity, provider changes, and continuity evidence periodically.

## Document Governance

| Control | Requirement |
| --- | --- |
| Document owner | LoraTrack Product Engineering |
| Business approver | Designated customer executive sponsor or service owner |
| Operational approver | Designated platform operations owner |
| Status | Public product documentation; approval evidence remains deployment-specific |
| Review cycle | At each published product-documentation change and at least annually |
| Version control | The public document version is maintained in `docs/VERSION` and incremented whenever published content changes |

## Executive Conclusion

LoraTrack provides a coherent technical foundation for governed asset inventory and location workflows. Production suitability cannot be determined from application controls alone. Approval requires deployment-specific evidence, field accuracy validation, service ownership, measurable targets, accepted residual risk, and demonstrated backup and recovery capability.
