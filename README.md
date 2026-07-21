# LoraTrack

LoraTrack is a Laravel dashboard for asset inventory and indoor/outdoor location using LoRaWAN and BLE telemetry. It normalizes external product catalogs, with SAP S/4HANA as the primary integration, and receives telemetry through TTI, MQTT, and Cisco Meraki.

The application supports multiple organizations and projects in a shared database. Products, SKUs, assets, devices, floor plans, zones, telemetry, alerts, users, and connectors are isolated by organization.

## Requirements

- PHP 8.2 or later
- Composer
- MariaDB 10.6 or later, or MySQL 8

## Installation

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

For local development:

```bash
composer dev
```

The interface uses Blade, static CSS, and native JavaScript. Node.js and npm are not required. Deferred work is executed by the Laravel scheduler:

```bash
php artisan schedule:run
```

Floor plans are stored exclusively under `storage/app/private` and delivered through authenticated routes. Do not create `public/storage` on the server.

The development seeder creates `test@example.com` with password `password` and the administrator role. Never retain this credential in a published environment.

## Organizations and Projects

Users gain access through organization memberships and may have a different role in each organization or project. Public registration creates an isolated organization from `/register` using an organization name, administrative email address, and password. Invitations may also add accounts to an existing organization. All subsequent queries are restricted to the active organization.

Connectors, MQTT consumers, TTI and Meraki webhooks, catalog synchronization, scheduled commands, and private files retain the organization identifier. Requests for resources owned by another organization return HTTP 404.

## Microsoft Sign-In

Register an application in Microsoft Entra ID and configure:

```dotenv
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_TENANT_ID=
MICROSOFT_REDIRECT_URI="http://localhost:8000/auth/microsoft/callback"
```

Public registration initially creates a local account. Microsoft sign-in requires an existing LoraTrack user with an authorized email address. On the first successful sign-in, LoraTrack associates the stable Microsoft identity with that user.

## Connectors

Administrators manage connectors from `/connectors`.

- Telemetry: TTI Webhook, Cisco Meraki Location API, and generic MQTT.
- Catalog: SAP S/4HANA, Microsoft Dynamics 365 Business Central, Shopify, Odoo, and CSV.

SAP S/4HANA, Business Central, Shopify, and Odoo catalog synchronization is implemented. CSV accepts `sku,name,external_id,description,base_unit,status`; `external_id` is optional and falls back to the SKU. Each service requires valid provider credentials and permissions before activation.

### TTI Webhook

1. Create a `TTI Webhook` connector and define a long, random token.
2. Activate the connector.
3. Configure a TTI webhook with the following destination:

```text
POST https://your-domain.example/api/v1/ingest/tti/{connector-ulid}
Authorization: Bearer {configured-token}
Content-Type: application/json
```

Events are authenticated, deduplicated, stored durably, and processed by the scheduler. The endpoint returns HTTP 202 after persistence.

Decoded payloads may contain lists under `observations`, `beacons`, `ble`, `scan`, or `devices`. Each observation must contain a MAC address (`mac`, `mac_address`, `address`, or `beacon_mac`) and RSSI (`rssi`, `signal`, or `signal_strength`). A two-dimensional position requires at least three active, non-collinear anchors installed on the same floor plan.

### Cisco Meraki Location API

Create a Meraki telemetry connector, configure the supported API major version and shared secret, and use the generated `/api/v1/ingest/meraki/{connector-ulid}` endpoint in Meraki. The HTTP endpoint stores a durable inbox record and responds immediately. Scheduled commands normalize each batch, create idempotent telemetry events, and process location observations.

### SAP S/4HANA

Configure the base URL, `API_PRODUCT_SRV` path, and Basic or Bearer authentication. Use **Test** before activation and **Synchronize** to request a scheduled import. Material numbers remain strings and preserve leading zeroes.

### MQTT

Configure the host, port, TLS mode, username, password, and topic through the connector. Keep the following listener supervised when MQTT connectors are enabled:

```bash
php artisan loratrack:mqtt-listen
```

The listener may be restricted to one connector by passing its ULID. Messages must be valid JSON and include MAC and RSSI fields accepted by the configured decoder.

### Payload Decoders

Administrators manage reusable profiles at `/payload-profiles`. A profile may be assigned to multiple products, match a format by FPort or a value inside `decoded_payload`, and map the following values through dot notation:

- observation list path;
- MAC field;
- RSSI field;
- optional receiver identifier.

Each profile has a priority, activation state, and preview using a complete TTI payload. The standard extractor remains available when no profile matches. User-supplied JavaScript is never executed.

## Floor Plans, Anchors, and Zones

The `/floor-plans` module supports:

- sites, buildings, and floors;
- PNG, JPG, WEBP, PDF, and DXF floor plans;
- raster previews for PDF and DXF sources;
- physical width and height in meters;
- fixed beacon and scanner placement;
- one-meter RSSI and environmental calibration;
- rectangular zones drawn with a pointer.

Positions are calculated through RSSI multilateration. When an estimate falls inside a zone, it is associated with that zone and may be presented together with its asset, product, and SKU. The dashboard retrieves new estimates periodically and identifies stale data.

## Assets, Authorization, and Alerts

Static and mobile assets are managed independently. Supported strategies include a fixed beacon for a static asset, a mobile beacon observed by fixed scanners, and a mobile tracker observing fixed beacons.

The application defines five role groups:

- `admin`: full access, including connectors, accounts, security, and audit records;
- `engineer`: floor plans, anchors, calibration, decoders, and technical health;
- `supervisor`: assets, alerts, and operational supervision;
- `operator`: registration, assignment, and daily asset tracking;
- `viewer`: read-only access to products, assets, floor plans, and maps.

Authorization is enforced by server-side routes and policies. Hiding a navigation option does not replace authorization.

Alert rules and recipients are configured under `/alerts`. Configure SMTP and run the scheduler every minute. Alert evaluation runs every ten minutes, suppresses duplicate notifications, and notifies again when an incident recurs after recovery.

## RSSI Calibration

Each floor plan provides a calibration workbench for authorized users. Select a fixed-beacon or fixed-scanner strategy, enter a physical `X/Y` reference point in meters, and provide median RSSI readings in dBm for at least four anchors. The following parameters may be adjusted per anchor:

- reference RSSI `A` measured at one meter, in dBm;
- dimensionless environmental path-loss exponent `n`;
- observed RSSI, in dBm.

The preview calculates coordinates, position error, RMSE, confidence, distances, and residuals in meters. It overlays expected and calculated positions on the floor plan. Parameters affect production calculations only after **Apply** is selected, and each calibration test remains available in the audit history.

## Operations and Diagnostics

Authorized technical roles can access `/operations/health` to review failed or delayed telemetry, connector status, private file state, anchor counts per floor plan, pending scanner placement, and recent audit records.

Web mutations generate audit entries containing the user, route, result, and `X-Request-ID`. Passwords, tokens, and connector credentials are excluded. Incoming telemetry has explicit payload size limits. Scheduler commands use idempotency and overlap protection.

Required production scheduling:

```cron
* * * * * cd /path/to/loratrack && php artisan schedule:run
```

When MQTT connectors are enabled, also supervise:

```bash
php artisan loratrack:mqtt-listen
```

## Verification

```bash
composer test
./vendor/bin/pint --test
composer audit
```

## Security and Deployment

Production environments require HTTPS, protected runtime secrets, least-privileged database credentials, scheduled backups, tested restoration procedures, access reviews, monitoring, and controlled change management. Follow the [Professional Deployment and Operations Guide](docs/LoraTrack-Deployment-Guide.pdf) before operating LoraTrack in production.

Technical controls alone do not constitute ISO certification, customer security approval, or independent assurance.

## Public Documentation

- [Technical Documentation and User Guide](docs/LoraTrack-Technical-Documentation.pdf)
- [Professional Deployment and Operations Guide](docs/LoraTrack-Deployment-Guide.pdf)
- [Documentation Index](docs/README.md)
