# Executive Technical Summary

## Purpose

LoraTrack is a web platform for inventory and asset location tracking in industrial indoor and outdoor environments. It manages products, physical assets, devices, floor plans, zones, connectors, IoT telemetry, and position estimates.

The application is implemented as a modular Laravel 12 monolith with Blade views, static CSS, and native JavaScript. It does not use a SPA framework or a Node.js frontend build pipeline.

## Main Capabilities

- Multi-organization and multi-project operation through organizations and memberships.
- Product and SKU catalog management, including external catalog synchronization.
- Physical asset registration and time-bound device assignment.
- Device registry for beacons, scanners, gateways, LoRaWAN trackers, and access points.
- 2D floor plans, rectangular zones, installed anchors, real dimensions in meters, and basic 3D support.
- Telemetry ingestion from TTI, generic MQTT, and Meraki Location.
- BLE payload normalization with MAC and RSSI observations.
- RSSI multilateration and Kalman filtering for position estimates.
- Operational map, historical asset track view, and dashboard.
- Alerts for offline assets, low confidence, and zone events.
- Web mutation audit log and operational health view.

## Technical Scope

LoraTrack becomes responsible once data is received from external providers. It does not implement a LoRaWAN network server and does not manage the LoRaWAN radio layer.

For LoRaWAN, The Things Industries manages devices, gateways, the network server, and webhook delivery. LoraTrack authenticates, deduplicates, stores, normalizes, and processes those events.

## Architecture Summary

- Runtime: PHP 8.2+.
- Framework: Laravel 12.
- Persistence: SQL Server, MariaDB, or MySQL depending on the deployment baseline.
- Queue: Laravel queue driver configured by `QUEUE_CONNECTION`.
- UI: Blade, static CSS, and native JavaScript.
- Inbound integrations: routes under `/api/v1/ingest`.
- Heavy processing: Laravel jobs.
- Scheduling: Laravel scheduler through cron, Task Scheduler, or a service manager.

## Critical Runtime Processes

Required background execution:

```bash
php artisan schedule:run
php artisan loratrack:mqtt-listen
```

Run `schedule:run` from cron every minute. The MQTT listener is only needed when MQTT connectors are enabled.

## Relevant Design Controls

- Business entities are isolated by `organization_id`.
- Effective roles are derived from organization memberships.
- Webhook tokens are stored as connector credentials.
- Connector credentials are encrypted by Laravel.
- Floor plan files are served from authenticated private storage.
- External events are deduplicated and processed idempotently.
- Web mutations are audited.
- Connector errors are sanitized.
- PHPUnit tests cover integrations, positioning, roles, multi-tenancy, and storage behavior.

## Known Limits

- RSSI accuracy depends on calibration, physical environment, and anchor quality.
- 2D positioning for mobile trackers requires at least three active, installed, non-collinear anchors on the same floor plan.
- The system must not be represented as certified metrology or a life-safety location system.
- Payload and observation retention must be formally defined per customer.
- Enterprise approval requires external evidence beyond the repository: access controls, backups, DRP, change management, infrastructure hardening, monitoring, and audit artifacts.
