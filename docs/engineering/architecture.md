# Solution Architecture

## Architectural Style

LoraTrack is organized as a modular Laravel monolith. Domain modules live under `app/` and are separated by models, controllers, jobs, services, and connector adapters. The architecture avoids premature microservices and prioritizes local transactions, traceability, and operational simplicity.

## Main Layers

| Layer | Responsibility | Examples |
| --- | --- | --- |
| Presentation | Blade views, forms, maps, progressive JavaScript | `resources/views`, `public/js`, `public/css` |
| HTTP | Authentication, authorization, validation, and responses | `app/Http/Controllers`, `app/Http/Requests`, `routes` |
| Application | Use cases, jobs, and commands | `app/Jobs`, `app/Console/Commands` |
| Domain | Models, positioning, telemetry, and connectors | `app/Models`, `app/Positioning`, `app/Telemetry`, `app/Connectors` |
| Persistence | Migrations, indexes, relationships, private storage | `database/migrations`, `storage/app/private` |

## Domain Modules

### Catalog

Manages products, SKUs, and external references. Imports are performed through catalog connectors and normalized into internal models.

Relevant classes:

- `App\Models\Product`
- `App\Models\Sku`
- `App\Models\ExternalProductReference`
- `App\Connectors\CatalogProductImporter`
- `App\Jobs\SyncCatalogConnector`

### Assets

Manages physical trackable instances. A product or SKU is not an asset; an asset has its own identity, photo, state, mobility, and current location.

Relevant classes:

- `App\Models\Asset`
- `App\Models\AssetDeviceAssignment`
- `App\Http\Controllers\AssetController`
- `App\Http\Controllers\AssetTrackController`

### Devices

Manages registered hardware and installations. An installation has time validity and coordinates on a floor plan.

Relevant classes:

- `App\Models\Device`
- `App\Models\DeviceInstallation`
- `App\Http\Controllers\DeviceController`

### Locations, Floor Plans, and Zones

Manages location hierarchy, raster plans, 3D models, zones, and anchors. Floor plans declare real width and height in meters.

Relevant classes:

- `App\Models\Location`
- `App\Models\FloorPlan`
- `App\Models\Zone`
- `App\Http\Controllers\FloorPlanController`
- `App\Positioning\ZoneClassifier`

### Telemetry

Receives, deduplicates, normalizes, and processes external events. Raw events are stored in `telemetry_events.raw_payload`; normalized data is stored in `normalized_payload`.

Relevant classes:

- `App\Models\TelemetryEvent`
- `App\Models\SignalObservation`
- `App\Jobs\ProcessTtiUplink`
- `App\Jobs\ProcessMerakiLocationObservation`
- `App\Telemetry\AssetLastSeenUpdater`
- `App\Telemetry\TelemetryCounterUpdater`

### Positioning

Converts RSSI observations into position estimates. Each estimate stores evidence, algorithm, version, confidence, and estimated accuracy.

Relevant classes:

- `App\Models\PositionEstimate`
- `App\Positioning\TelemetryPositioningService`
- `App\Positioning\RssiMultilateration`
- `App\Positioning\KalmanPositionFilter`
- `App\Positioning\BleObservationExtractor`

### Connectors

Manages configured provider instances for telemetry and catalog synchronization. Non-secret configuration is stored separately from encrypted credentials.

Relevant classes:

- `App\Models\Connector`
- `App\Models\ConnectorActivityLog`
- `App\Connectors\ConnectorRegistry`
- `App\Connectors\ConnectorConnectionTester`

### Identity and Security

Manages users, organizations, memberships, roles, local login, Microsoft OAuth, and server-side authorization.

Relevant classes:

- `App\Models\User`
- `App\Models\Organization`
- `App\Models\OrganizationMembership`
- `App\Enums\UserRole`
- `App\Http\Middleware\SetOrganizationContext`
- `App\Http\Middleware\EnsureUserHasPermission`

## TTI Telemetry Flow

1. TTI sends `POST /api/v1/ingest/tti/{connector}` with a Bearer token.
2. `TtiWebhookController` validates size, token, minimum schema, and active connector status.
3. `external_event_id` is calculated from device, session, frame counter, and provider timestamp.
4. A `telemetry_events` row is created in `pending` state.
5. `ProcessTtiUplink` is dispatched.
6. The job creates or updates the tracker device, normalizes the payload, extracts BLE observations, and updates `signal_observations`.
7. `AssetLastSeenUpdater` updates the assigned asset when applicable.
8. `TelemetryPositioningService` attempts to create `position_estimates`.
9. The event becomes `processed`, `failed`, or `ignored`.

## Asset Track Flow

1. The user opens `/assets/{asset}/track`.
2. `AssetTrackController` selects floor plans with historical positions.
3. `asset-track.js` requests `/assets/{asset}/track/data`.
4. The endpoint returns `position_estimates` filtered by asset, floor plan, and time range.
5. The browser draws polylines, points, accuracy, and tooltips.

The track view does not read raw `telemetry_events`. If an uplink does not produce a `position_estimates` row, it does not appear as a track point.

## External Dependencies

- Laravel Framework.
- Laravel Socialite and Socialite Microsoft provider.
- `php-mqtt/client` for MQTT.
- Vendored Select2 and jQuery for remote selectors.
- SQL Server, MariaDB, or MySQL depending on the deployment.
- External services: TTI, Meraki, SAP, Business Central, Shopify, and Odoo.

## Evolution Principles

- Keep the internal domain independent from provider-specific payload formats.
- Process heavy integrations through queues.
- Store enough evidence for auditability and reproducibility.
- Do not mix geographic coordinates with local floor-plan coordinates.
- Prefer persisted counters and aggregates for operational screens.
