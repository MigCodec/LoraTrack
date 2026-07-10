# Security Assurance Baseline

This baseline organizes technical evidence for an information security management system. It does not claim certification or conformity with unpublished customer requirements.

| Control Area | Repository Evidence | External Evidence Still Required |
| --- | --- | --- |
| Secure development | Pull-request CI, tests, Pint, dependency audit | PHP-compatible SAST, SDLC policy, reviewer independence, training records |
| Access control | Tenant isolation, server authorization, protected production Environment | Identity lifecycle, MFA/SSO, quarterly access reviews |
| Secrets | `.gitignore`, GitHub secrets, protected `.env`, no PAT in clone URL | Vault ownership, rotation schedule, break-glass process |
| Supply chain | Locked Composer dependencies, Dependabot, pinned Actions | Supplier assessment, SBOM retention, exception process |
| Change management | Immutable commit deployment, required checks and approval | Change tickets, segregation of duties, emergency-change review |
| Logging | Request IDs, audit records, connector activity | Central SIEM, retention, monitoring and response ownership |
| Data protection | Private files, encrypted connector credentials, tenant scoping | Classification, retention/deletion rules, privacy assessment |
| Availability | Maintenance mode and bounded deployment | Backups, restore evidence, RTO/RPO, disaster recovery exercises |
| Vulnerability management | Composer audit, Dependabot, private reporting policy | PHP-compatible SAST, remediation SLAs, penetration tests, risk acceptance |
| Incident response | Private disclosure route | Incident plan, contacts, exercises, notification obligations |

Before customer production approval, obtain the applicable security baseline, data classification rules, architecture review, privacy requirements, vendor risk questionnaire, penetration test scope, logging/SIEM requirements, and documented acceptance by the authorized security owner.
