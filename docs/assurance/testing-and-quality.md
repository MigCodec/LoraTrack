# Testing, Quality, and Assurance

## Test Stack

- PHPUnit 11.
- Laravel testing helpers.
- User factories.
- Feature and unit tests.
- Laravel Pint for PHP formatting.

Commands:

```bash
php artisan test
composer test
./vendor/bin/pint --test
composer audit
```

## Existing Functional Coverage

The suite includes tests for:

- authentication;
- role authorization;
- multi-tenancy;
- organization management;
- invitations;
- connectors;
- SAP;
- TTI webhook;
- TTI positioning;
- Meraki Location;
- floor plans and zones;
- devices;
- assets, permissions, and alerts;
- asset track;
- calibration;
- payload profiles;
- telemetry storage management;
- Kalman filters and RSSI multilateration;
- brand palette.

## Critical Tests by Domain

### Telemetry

Test:

- webhook authentication;
- deduplication;
- invalid payloads;
- duplicate events;
- disabled connector behavior;
- asynchronous processing;
- MAC/RSSI extraction;
- `pending`, `processed`, `failed`, and `ignored` states.

### Positioning

Test:

- three valid anchors;
- fewer than three anchors;
- collinear anchors;
- invalid RSSI;
- out-of-plan coordinates;
- Kalman filter;
- zone classification;
- stored evidence.

### Security

Test:

- organization isolation;
- role permissions;
- private file access;
- unauthorized route rejection;
- connector secrets not exposed;
- login and webhook throttling.

### Integrations

Test:

- sanitized fixtures per provider;
- pagination;
- transient errors and rate limits;
- idempotent upsert;
- cursor or checkpoint behavior;
- sanitized errors.

## Code Quality

Rules:

- small controllers;
- Form Requests where applicable;
- jobs for heavy processing;
- never log secrets;
- tenant-aware queries;
- comments only when they add context;
- reversible migrations when safe.

## Acceptance Criteria for Changes

A change is ready when it:

- respects domain boundaries;
- includes required migrations and indexes;
- preserves integration idempotency;
- preserves traceability;
- enforces authorization;
- includes risk-proportionate tests;
- passes Pint;
- documents new contracts or relevant decisions.

## Enterprise Review Evidence

Recommended evidence package:

- automated test report;
- deployed commit SHA;
- `composer audit` result;
- dependency list;
- role matrix;
- architecture diagram;
- backup and restore policy;
- change management procedure;
- hardening evidence;
- monitoring evidence;
- incident log if applicable.

## Limitations

Automated tests validate software behavior, but not:

- real infrastructure configuration;
- network controls;
- environment segregation;
- external backups;
- human support processes;
- formal compliance certification.
