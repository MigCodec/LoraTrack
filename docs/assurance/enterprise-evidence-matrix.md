# Enterprise Evidence Matrix

This matrix summarizes evidence an industrial enterprise may request before approving a pilot, production deployment, or corporate integration.

| Area | Expected Evidence | Repository Source | External Evidence Required |
| --- | --- | --- | --- |
| Architecture | Components, flows, dependencies | `docs/engineering/architecture.md` | Approved architecture diagram and review |
| Data | Domain model, tables, traceability | `docs/engineering/domain-and-data-model.md` | Data classification and RACI |
| Security | Roles, authentication, secrets, isolation | `docs/engineering/security-and-identity.md` | Hardening, penetration test, IAM review |
| Integrations | TTI, Meraki, SAP, MQTT contracts | `docs/engineering/integrations.md` | Credentials, permissions, provider approvals |
| Operations | Runbooks, monitoring, cron, queues | `docs/operations/operations-runbook.md` | NOC/SOC procedures, SLA, escalation |
| Deployment | Requirements, variables, rollback | `docs/operations/deployment-and-environments.md` | Pipeline, change control, approvers |
| Testing | PHPUnit, Pint, dependency audit | `docs/assurance/testing-and-quality.md` | CI report, UAT evidence, load test |
| Privacy | Sensitive data and retention | `docs/engineering/security-and-identity.md` | DPIA/PIA if applicable |
| Continuity | Backup and restore | `docs/operations/deployment-and-environments.md` | Restore test and approved RTO/RPO |
| Incidents | Escalation and evidence collection | `docs/operations/operations-runbook.md` | Incident response plan and contacts |
| Compliance | Limit statement | `SECURITY.md` | Formal audit, certifications, policies |

## Due Diligence Questions

### Is the system multi-tenant?

Yes. It uses organizations, memberships, and `organization_id` on business entities. Authorization is enforced server-side.

### Where are floor plans stored?

In private storage under `storage/app/private`, served through authenticated routes.

### Are raw payloads stored?

Currently, `telemetry_events.raw_payload` stores the received event. Retention must be defined by policy. Raw payloads are considered sensitive.

### How is reprocessing avoided?

Each connector uses a deduplicated external identity. TTI identity is calculated from device identifiers, session, frame counter, and provider timestamp.

### What happens if a connector is disabled?

Inactive connectors reject new webhooks. If an event was already queued and the connector is disabled, the job marks it `ignored`.

### Is the system real time?

It operates near real time, constrained by uplink arrival and queue processing. It must not be represented as a life-safety or certified tracking system.

### How is accuracy measured?

Each estimate stores `confidence`, `accuracy_meters`, and evidence. Accuracy depends on calibration and environment.

### What tests exist?

Feature and unit tests cover critical domains. See `tests/` and `docs/assurance/testing-and-quality.md`.

### What is still required for corporate approval?

Real-environment evidence: hardening, monitoring, backups, DRP, IAM, network controls, security review, load testing, and support procedures.

## Customer Delivery Checklist

- [ ] Documentation index delivered.
- [ ] Architecture diagram validated.
- [ ] Routes and integrations documented.
- [ ] Role matrix reviewed.
- [ ] Retention policy approved.
- [ ] Runbooks reviewed.
- [ ] Backup and restore plan tested.
- [ ] CI evidence attached.
- [ ] Deployment evidence attached.
- [ ] Secrets inventory and owners defined.
- [ ] Support and escalation contacts defined.
- [ ] Accepted risks signed off.
