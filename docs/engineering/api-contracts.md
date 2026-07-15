# Internal and External API Contracts

## Scope

This document summarizes relevant HTTP routes for integrations, operations, and visualization. It is not a formal OpenAPI specification, but it provides a review baseline for engineering teams.

## Conventions

- Web routes require an authenticated session unless stated otherwise.
- Ingestion routes use `/api/v1`.
- Exposed identifiers are generally ULIDs.
- Error responses follow Laravel behavior unless explicitly customized.
- Examples are sanitized.

## TTI Ingestion

```text
POST /api/v1/ingest/tti/{connector}
```

Headers:

```text
Authorization: Bearer {webhook_token}
Content-Type: application/json
Accept: application/json
```

Minimum payload:

```json
{
  "end_device_ids": {
    "device_id": "tracker-01",
    "dev_eui": "0011223344556677"
  },
  "uplink_message": {
    "f_port": 1,
    "f_cnt": 42,
    "received_at": "2026-07-10T11:24:49Z",
    "decoded_payload": {
      "beacons": [
        {"mac": "AA:BB:CC:DD:EE:01", "rssi": -76},
        {"mac": "AA:BB:CC:DD:EE:02", "rssi": -78},
        {"mac": "AA:BB:CC:DD:EE:03", "rssi": -80}
      ]
    }
  }
}
```

Accepted response:

```json
{
  "accepted": true,
  "duplicate": false,
  "event_id": "01...",
  "request_id": "..."
}
```

Status codes:

- `202`: accepted.
- `401`: invalid token.
- `404`: connector not found, inactive, or wrong provider.
- `413`: payload larger than 1 MB.
- `422`: validation failure.
- `429`: throttled.

## Meraki Ingestion

Receiver validation:

```text
GET /api/v1/ingest/meraki/{connector}
```

Reception:

```text
POST /api/v1/ingest/meraki/{connector}
```

The connector must be active and configured. Internal normalization produces `telemetry_events`, `signal_observations`, access point devices, and positions when mapping is sufficient.

Rejected Meraki POST requests are recorded separately from accepted telemetry. LoraTrack retains only the 10 most recent rejections per connector with the HTTP status, sanitized reason, declared version/type, request ID, and a keyed hash of the source IP. Shared secrets and complete request payloads are never stored in this diagnostic history. These records do not increment the failed telemetry counter because no `telemetry_event` was accepted.

The connector detail counters link to filtered accepted telemetry (`received`, `processed`, `pending`, or `failed`) or to the separate rejected-request history.

## Map Data

```text
GET /map/{floorPlan}/data
```

Authorization:

- authenticated session;
- `maps.view` permission.

Conceptual response:

```json
{
  "anchors": [
    {
      "id": "01...",
      "name": "Beacon A",
      "identifier": "AABBCCDDEE01",
      "type": "beacon",
      "x": 0.1,
      "y": 0.2
    }
  ],
  "positions": [
    {
      "asset_id": "01...",
      "name": "Pump 01",
      "x": 0.5,
      "y": 0.4,
      "x_meters": 5.0,
      "y_meters": 4.0,
      "confidence": 0.91,
      "accuracy_meters": 1.2,
      "calculated_at": "2026-07-10T11:24:50Z",
      "evidence": []
    }
  ]
}
```

## Asset Track

View:

```text
GET /assets/{asset}/track
```

Data:

```text
GET /assets/{asset}/track/data?floor_plan_id={id}&range=24h
```

Parameters:

- `floor_plan_id`: optional.
- `range`: `1h`, `24h`, `7d`, `30d`.
- `from`: optional date.
- `to`: optional date.
- `after`: optional incremental update date.

The response contains asset metadata, floor plan metadata, and historical `position_estimates`.

## Connector Administration

Admin web routes:

- `GET /connectors`
- `GET /connectors/{connector}`
- `GET /connectors/create/{provider}`
- `POST /connectors`
- `POST /connectors/{connector}/test`
- `POST /connectors/{connector}/toggle`
- `POST /connectors/{connector}/rotate-webhook-token`
- `POST /connectors/{connector}/sync`
- `POST /connectors/{connector}/csv`

Secrets must not be returned in full to the browser after storage.

## Floor Plans, Zones, and Installations

Relevant routes:

- `GET /floor-plans`
- `GET /floor-plans/{floorPlan}/file`
- `GET /floor-plans/{floorPlan}/model`
- `POST /floor-plans`
- `PUT /floor-plans/{floorPlan}`
- `POST /floor-plans/{floorPlan}/zones`
- `POST /floor-plans/{floorPlan}/installations`
- `PUT /installations/{deviceInstallation}`

Mutation permission:

```text
plans.manage
```

## Assets

Relevant routes:

- `GET /assets`
- `GET /assets/{asset}/photo`
- `POST /assets`
- `PUT /assets/{asset}`
- `POST /assets/{asset}/assignments`
- `DELETE /asset-assignments/{assignment}`
- `POST /assets/{asset}/refresh-position`

Mutation permission:

```text
assets.manage
```

## Error Observability

For investigations, correlate:

- `X-Request-ID` when present;
- `telemetry_events.id`;
- `connectors.id`;
- `jobs.uuid` when applicable;
- `observed_at`, `received_at`, and `processed_at`;
- Laravel logs.

## Future OpenAPI Requirements

- Versioned schemas per endpoint.
- Stable error code catalog.
- Pagination and filter documentation.
- Permission documentation per route.
- Sanitized examples per provider.
- Outbound webhook contracts if added.
