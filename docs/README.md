# LoraTrack Documentation

This directory publishes the functional, technical, deployment, security, and operational documentation for LoraTrack consumers and platform operators.

The documentation describes the software state represented by this repository. It is not an ISO certification, customer approval, independent cybersecurity report, or substitute for a formal audit.

## Audience

- Application users and organization administrators.
- Systems engineering, OT/IT, and integration teams.
- Cybersecurity and enterprise architecture teams.
- Platform administrators and operations support teams.
- Industrial customer reviewers who require technical traceability.

## Published Documents

- [Technical Documentation and User Guide](LoraTrack-Technical-Documentation.pdf)
- [Professional Deployment and Operations Guide](LoraTrack-Deployment-Guide.pdf)
- [User Guide](user-guide.md)

## Documentation Map

- [Executive Technical Summary](engineering/executive-technical-summary.md)
- [Solution Architecture](engineering/architecture.md)
- [Domain and Data Model](engineering/domain-and-data-model.md)
- [Telemetry and Positioning](engineering/telemetry-and-positioning.md)
- [External Integrations and Contracts](engineering/integrations.md)
- [Internal and External API Contracts](engineering/api-contracts.md)
- [Security, Identity, and Tenant Isolation](engineering/security-and-identity.md)
- [Operations, Monitoring, and Runbooks](operations/operations-runbook.md)
- [Deployment and Environment Configuration](operations/deployment-and-environments.md)
- [Dependency Matrix](operations/dependency-matrix.md)
- [Compliance Baseline and Benchmarks](operations/compliance-baseline.md)
- [Recommended SQL Server Baseline](operations/sql-server.md)
- [Ubuntu LTS Deployment Tutorial](operations/deployment-ubuntu-lts.md)
- [Windows Server and IIS Deployment Tutorial](operations/deployment-windows-iis.md)
- [Field Commissioning Guide](operations/field-commissioning.md)

## PDF Generation

The repository includes a generator that publishes two documents with stable names directly under `docs/`:

```bash
python tools/build_docs_pdf.py --version 1.0 --engine playwright
```

Published files:

- `LoraTrack-Technical-Documentation.pdf`, `.html`, and `.md`;
- `LoraTrack-Deployment-Guide.pdf`, `.html`, and `.md`.

The documents do not include a commit SHA, branch name, commit message, local path, or internal workflow metadata.

Supported PDF engines:

- WeasyPrint: `pip install markdown weasyprint`
- Playwright: `pip install markdown playwright && python -m playwright install chromium`
- Pandoc: a local `pandoc` installation with a compatible PDF engine.

GitHub Actions regenerates the publications whenever their source documentation changes. On `main`, updated files are committed directly under `docs/` and do not depend on temporary Actions artifacts.

## Product Integration Documents

- [TTI Integration](integrations/tti.md)
- [SAP Integration](integrations/sap.md)

## Conventions

- Class names, table names, commands, and route names are documented exactly as implemented.
- Command examples assume execution from the project root.
- Production references must be adapted to the hosting model, domain, database, and change controls defined by the documented compliance baseline.
- Statements concerning standards identify alignment targets or control references; they do not claim certification without independent evidence and formal approval.
