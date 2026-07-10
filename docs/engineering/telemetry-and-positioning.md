# Telemetry and Positioning

## Objective

Convert external provider events into normalized observations and, when evidence is sufficient, estimate asset positions on floor plans with real-world dimensions.

## Telemetry States

`telemetry_events.processing_status` may be:

- `pending`: event received and not yet processed.
- `processed`: job completed successfully.
- `failed`: job failed and stores a sanitized error.
- `ignored`: event intentionally not processed, for example because the connector was disabled.

## TTI Ingestion

Endpoint:

```text
POST /api/v1/ingest/tti/{connector}
Authorization: Bearer {token}
Content-Type: application/json
```

Main validations:

- Maximum payload size is 1 MB.
- Connector must be active and of provider `TTI Webhook`.
- Bearer token must match the configured credential.
- `end_device_ids` and `uplink_message` must be present.
- `uplink_message.received_at` or `received_at` is used as provider time when available.

The endpoint returns `202 Accepted` and dispatches asynchronous processing.

## TTI Processing

Job: `App\Jobs\ProcessTtiUplink`

Responsibilities:

1. Set organization context.
2. Skip events already marked as `processed`.
3. Mark queued events as `ignored` if the connector was disabled.
4. Create or update the tracker device.
5. Apply a decoder profile when one matches.
6. Store `normalized_payload`.
7. Extract BLE MAC/RSSI observations.
8. Update `last_seen_at` for the assigned asset.
9. Run positioning.
10. Mark the event as processed or failed.

## BLE Extraction

Class: `App\Positioning\BleObservationExtractor`

Accepted observation fields:

- MAC: `mac`, `mac_address`, `address`, `beacon_mac`.
- RSSI: `rssi`, `signal`, `signal_strength`.

MAC addresses are normalized to uppercase hexadecimal without separators. RSSI must be numeric, negative, and not less than -127 dBm.

## Tracking Strategies

### Mobile Beacon, Fixed Scanners

A BLE beacon is assigned to a mobile asset. Fixed scanners detect its MAC. The system groups recent observations by receiver and calculates position against `scanner` installations.

Strategy: `mobile_beacon_fixed_scanners`

### Fixed Beacons, Mobile Tracker

A LoRaWAN tracker is assigned to a mobile asset. The tracker reports detected fixed beacons. The system calculates position using `beacon` installations.

Strategy: `fixed_beacons_mobile_tracker`

## Conditions Required to Generate a Position

An event creates a `position_estimates` row only when:

- the event has `device_id`;
- the event has signal observations;
- the device is actively assigned to an asset;
- the tracking strategy matches;
- at least three active anchors of the correct type exist;
- anchors have `x/y` coordinates;
- anchors belong to the same `floor_plan_id`;
- geometry is not degenerate for multilateration.

If any condition is missing, the event may still be `processed` without creating a position.

## Algorithm

RSSI positioning uses:

- `RssiMultilateration`: approximates position from RSSI measurements.
- `KalmanPositionFilter`: smooths successive positions.
- `ZoneClassifier`: assigns a position to rectangular zones.

Each `position_estimates` row stores:

- `raw_x`, `raw_y`: pre-filter result.
- `x`, `y`: filtered result.
- `confidence`.
- `accuracy_meters`.
- `evidence`: anchors, RSSI, distances, and residuals.
- `filter_state`.

## Asset Track View

The asset track screen queries only `position_estimates`. It does not draw raw `telemetry_events`.

Applied filters:

- asset;
- selected floor plan;
- time range based on `calculated_at`;
- optional `after` timestamp for live updates.

If new uplinks arrive but no new track point appears, check:

1. `telemetry_events.processing_status`.
2. number of `signal_observations`.
3. asset-device assignment.
4. active and installed beacons/scanners.
5. selected floor plan and time range.
6. `position_estimates` for the telemetry event.

## Operational Diagnostic SQL

Recent events:

```sql
select id, device_id, observed_at, received_at, processed_at,
       processing_status, processing_error
from telemetry_events
order by received_at desc
limit 20;
```

Observations by event:

```sql
select telemetry_event_id, count(*) as signals
from signal_observations
group by telemetry_event_id
order by max(observed_at) desc
limit 20;
```

Recent positions:

```sql
select id, asset_id, telemetry_event_id, floor_plan_id,
       calculated_at, x, y, confidence, accuracy_meters
from position_estimates
order by calculated_at desc
limit 20;
```

## Position Quality

RSSI location is an operational estimate, not certified metrology. Quality depends on:

- anchor calibration;
- multipath and site materials;
- antenna height and orientation;
- anchor density and distribution;
- firmware and payload format;
- uplink frequency and time window.

## Retention

Telemetry storage management commands:

- `loratrack:manage-telemetry-storage`
- `loratrack:prune-meraki-history`

The exact retention policy must be defined by customer contract and risk assessment.
