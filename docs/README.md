# LoraTrack - Engineering Documentation

This directory contains the technical documentation for LoraTrack, intended for engineering, security, operations, and enterprise assurance reviews.

The documentation describes the software state observed in this repository. It is not an ISO certification, customer approval, independent cybersecurity report, or substitute for a formal audit.

## Audience

- Systems engineering, OT/IT, and integration teams.
- Cybersecurity and enterprise architecture teams.
- Platform administrators and operations support teams.
- Industrial customer reviewers requiring technical traceability.

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
- [Testing, Quality, and Assurance](assurance/testing-and-quality.md)
- [Enterprise Evidence Matrix](assurance/enterprise-evidence-matrix.md)
- [Risks, Limits, and Open Decisions](engineering/risks-and-open-decisions.md)

## PDF Generation

The repository includes a Python generator that publishes this documentation as a versioned HTML/PDF package:

```bash
python tools/build_docs_pdf.py --version 1.0.0
```

Output is written to `dist/docs/` and includes:

- combined Markdown;
- print-ready HTML;
- PDF when an engine is available;
- a manifest with version, commit, branch, and included documents.

Supported PDF engines:

- WeasyPrint: `pip install markdown weasyprint`
- Playwright: `pip install markdown playwright && python -m playwright install chromium`
- Pandoc: local `pandoc` installation plus a compatible PDF engine.

GitHub Actions also builds the package through `.github/workflows/docs.yml` whenever documentation changes on `main`, on documentation pull requests, or through manual dispatch. The workflow uploads `dist/docs/` as a downloadable artifact containing Markdown, HTML, PDF, and manifest files.

## Related Existing Documents

- [TTI Integration](integrations/tti.md)
- [SAP Integration](integrations/sap.md)
- [Secure Deployment](security/deployment.md)
- [Existing Assurance Matrix](security/assurance.md)
- [Security Policy](../SECURITY.md)
- [Repository Architecture Rules](../AGENTS.md)

## Conventions

- Class names, table names, commands, and route names are documented exactly as they exist in the repository.
- Command examples assume execution from the project root.
- Production references must be adapted to the hosting model, domain, database, and change controls defined by the documented compliance baseline.
