# ADR 0002: Cisco Meraki Location API Integration

## Context

Meraki Scanning/Location API v2.1 and v3.x provide WiFi/BLE observations, RSSI, and calculated positions. Their floor plan identifiers and coordinate system do not belong to the internal LoraTrack domain.

## Decision

- Implement Meraki as an independent telemetry provider.
- Select contract major version per connector instance and accept compatible minor versions.
- Authenticate each POST through the shared secret in the payload and expose the validation GET required by Meraki.
- Persist an auditable observation per client/position and process it idempotently through the queue.
- Register devices by normalized MAC and generate positions only when a time-bound asset assignment exists.
- Maintain a connector-scoped mapping table between Meraki floor plan IDs and LoraTrack floor plans.
- Convert Meraki's bottom-origin Y axis to the web editor's top-origin axis by default.
- Preserve `variance`/`unc` as provider-reported accuracy, without presenting it as accuracy calculated by LoraTrack.
- Compact v3 payloads after processing: keep checksum, counts, BLE identity, last reading, and APs contributing RSSI, without duplicating all original `reportingAps` or locations.
- Retain the last ten Meraki events per organization, connector, and device. Derived positions remain independent history.

## Alternatives Considered

- Map floor plans by name: rejected because of ambiguity and name changes.
- Store the Meraki ID directly on `floor_plans`: rejected because a floor plan can relate to multiple connectors.
- Always recalculate position from RSSI: rejected because it would lose the provider estimate and reported accuracy.

## Consequences

Each Meraki floor plan must be mapped before local coordinates can be displayed. Without mapping, LoraTrack still records MAC, observations, and available geographic coordinates.
