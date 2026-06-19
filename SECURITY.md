# Security policy

## Reporting a vulnerability

Do not disclose suspected vulnerabilities in public issues, discussions, pull requests, screenshots, or logs. Use GitHub Private Vulnerability Reporting for this repository. Repository administrators must enable it under **Settings → Security → Private vulnerability reporting** before publication.

Include the affected version or commit, reproducible steps, impact, and a minimal proof of concept without real credentials or personal data. Receipt should be acknowledged within two business days. Remediation targets depend on risk and must be recorded in the private advisory.

## Supported versions

Only the current `main` branch and the currently deployed release receive security fixes until a formal release-support policy is published.

## Secrets and sensitive data

- Never commit `.env`, connector credentials, SSH material, database exports, production payloads, customer floor plans, logs, or personal data.
- Use GitHub Environment secrets for deployment credentials and protected server-side `.env` files for runtime secrets.
- Rotate a secret immediately if it reaches Git history; deleting the current file is not sufficient.
- Use sanitized fixtures in tests and documentation.

## Security boundary

The public source code and automated controls support security assurance, but do not constitute ISO certification or approval by a customer. Certification also requires governance, asset and risk registers, access reviews, supplier management, incident response, backups, business continuity, training, evidence retention, and independent audit.
