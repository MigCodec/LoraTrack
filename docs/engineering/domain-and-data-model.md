# Domain and Data Model

## Core Concepts

| Concept | Description |
| --- | --- |
| Organization | Business tenant or project. Isolates operational data. |
| User | Local or Microsoft-linked access account. |
| Membership | User-to-organization relationship with effective role. |
| Product | Commercial catalog definition. |
| SKU | Product code or catalog variant. |
| Asset | Physical trackable instance. |
| Device | Field hardware such as beacon, scanner, tracker, gateway, or AP. |
| Assignment | Time-bound asset-to-device relationship. |
| Installation | Time-bound fixed device placement on a floor plan. |
| Telemetry | External event received from TTI, Meraki, MQTT, or another provider. |
| Signal observation | MAC/RSSI observation detected by a receiver. |
| Position estimate | Calculated result with algorithm, evidence, and accuracy. |
| Floor plan | Raster or 3D representation of a physical space with real dimensions. |
| Zone | Region inside a floor plan. |
| Connector | Configured external provider instance. |

## Relevant Tables

### Organizations and Identity

- `organizations`: name, slug, branding, operational settings.
- `organization_memberships`: user, organization, and role.
- `organization_invitations`: invitations with token and expiration.
- `users`: account, email, password, and `microsoft_id`.

### Catalog

- `products`: normalized product.
- `skus`: SKU code and product reference.
- `external_product_references`: provider/external ID mapping.

### Assets and Devices

- `assets`: physical asset, tag, name, mobility, status, photo, `last_seen_at`.
- `devices`: hardware identity, type, status, metadata, `last_seen_at`.
- `asset_device_assignments`: time-bound asset-device assignment and tracking strategy.
- `device_installations`: fixed device placement with coordinates, floor plan, and RSSI parameters.

### Locations and Floor Plans

- `locations`: sites, buildings, floors, or other hierarchy levels.
- `floor_plans`: private files, real dimensions, 2D/3D configuration.
- `zones`: rectangles in normalized coordinates.

### Telemetry and Positioning

- `telemetry_events`: external event, raw payload, normalized payload, processing status.
- `signal_observations`: MAC/RSSI observations associated with an event.
- `position_estimates`: calculated asset positions tied to telemetry events.
- `calibration_runs`: RSSI calibration history.

### Connectors and Audit

- `connectors`: provider instance, status, configuration, encrypted credentials, counters.
- `connector_activity_logs`: connector activity log.
- `audit_logs`: web mutation audit trail.

### Alerts

- `alert_settings`: organization-level alert settings.
- `alerts`: alert events.
- `alert_rules`: configurable alert rules.
- `zone_alert_rules`: zone-specific rules.
- `zone_presence_states`: current zone presence state by asset.

## Multi-Tenancy

Business entities use `organization_id` and the `BelongsToOrganization` trait. The active context is resolved through `SetOrganizationContext`.

Expected rules:

- A user only sees data from the active organization.
- Roles are derived from memberships, not from a global role.
- Routes must enforce server-side authorization.
- `unique` and `exists` validation rules must be tenant-aware where applicable.
- Jobs and webhooks must set organization context before operating.

## Time and Traceability

Telemetry stores separate timestamps:

- `observed_at`: provider or device time, normalized.
- `received_at`: time when LoraTrack received the event.
- `processed_at`: time when the job completed.

Positions store:

- `calculated_at`: calculation time.
- `telemetry_event_id`: source event.
- `evidence`: anchors, RSSI, estimated distances, and residuals.
- `algorithm` and `algorithm_version`.
- `confidence` and `accuracy_meters`.

## Event Identity

TTI events are deduplicated using a hash built from:

- `end_device_ids.dev_eui`
- `end_device_ids.device_id`
- `uplink_message.session_key_id`
- `uplink_message.f_cnt`
- `uplink_message.received_at` or `received_at`

This avoids reprocessing provider retries without collapsing successive uplinks.

## Data Governance Notes

- Define retention for `raw_payload` formally.
- Treat floor plans and asset locations as sensitive site information.
- Avoid exposing raw payloads in logs or broad UI views.
- Define contractual/legal basis for location data.
- Define export and deletion procedures for contract termination.
