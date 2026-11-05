# TTI Integration

LoraTrack receives uplinks from The Things Stack through HTTPS. TTI remains responsible for the LoRaWAN network, gateways, devices, and delivery.

## Inbound Contract

- Endpoint: `POST /api/v1/ingest/tti/{connector}`
- Authentication: `Authorization: Bearer <token>`
- Accepted response: HTTP 202
- Idempotent identity: stable hash of device, session, frame counter, and TTI timestamp

LoraTrack stores `raw_payload`, receive time, and the normalized version. The job extracts `decoded_payload`, `frm_payload`, `rx_metadata`, frame counter, port, and device information.

## Fixed BLE Scanner Contract

The built-in formatter `tti_beacon_scan` version `1.0` recognizes uplinks whose `uplink_message.decoded_payload.type` is `beacon_scan`.

- The LoRaWAN `dev_eui` identifies a fixed `scanner` device. The TTI `device_id` is used only as a fallback when the EUI is absent.
- Each entry under `decoded_payload.beacons` produces one normalized signal observation with `transmitter_mac`, integer `rssi`, the fixed receiver identifier, and the original sanitized entry as metadata.
- MAC addresses are stored as uppercase hexadecimal without separators.
- The scan's `time_utc` (or Unix `timestamp`) becomes the event observation time. Each beacon's own time becomes its observation time.
- TTI delivery timestamps remain in the raw auditable payload and the application stores its own receipt time separately. Delayed and out-of-order delivery does not rewrite the sensor observation time.
- Invalid MAC/RSSI entries are ignored. Reprocessing uses the event and transmitter uniqueness constraint and does not duplicate observations.

The corresponding mobile BLE tag must be registered as a `beacon` and assigned to an asset with the `mobile_beacon_fixed_scanners` strategy. The scanner must have an active fixed installation before it can contribute to positioning. At least three fixed scanners with compatible coordinates are still required for a 2D estimate.

Reference: <https://www.thethingsindustries.com/docs/integrations/webhooks/>
