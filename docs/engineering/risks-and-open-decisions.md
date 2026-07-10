# Risks, Limits, and Open Decisions

## Technical Risks

### RSSI Accuracy

Risk: accuracy can vary significantly because of interference, multipath, antenna orientation, and anchor density.

Mitigation:

- per-floor calibration;
- evidence stored per estimate;
- confidence thresholds;
- no false precision claims;
- field testing.

### Queue Not Running

Risk: webhooks accept events, but events remain `pending` if the worker is not running.

Mitigation:

- queue monitoring;
- cron or service supervisor;
- `/operations/health`;
- alerts for stuck telemetry.

### Database Connection Exhaustion

Risk: overlapping cron workers can exhaust `max_user_connections`.

Mitigation:

- `--stop-when-empty`;
- `--max-time`;
- avoid duplicate cron jobs;
- prefer a supervised worker when possible;
- adjust database limits when justified by load.

### Sensitive Raw Payloads

Risk: `raw_payload` may contain sensitive or high-volume data.

Mitigation:

- retention policy;
- scheduled cleanup;
- future split into a dedicated table if required;
- restricted access to event views.

### Provider Dependency

Risk: TTI, Meraki, SAP, or other provider format changes may break normalization.

Mitigation:

- decoder profiles;
- sanitized fixtures;
- contract tests;
- API version documentation.

## Functional Limits

- Does not manage LoRaWAN networking.
- Does not guarantee MQTT QoS 0 delivery.
- Does not replace a life-safety system.
- Does not certify metrological location accuracy.
- Does not by itself provide ISO/SOC organizational compliance.
- Does not currently include formal load testing artifacts in the repository.

## Open Decisions

- Final retention policy for `raw_payload`.
- Dedicated table for raw payloads with per-device caps.
- Production RTO/RPO.
- Monitoring strategy aligned with ISO/IEC 20000-1 and ISO/IEC 27001.
- Expected scale by customer.
- Certified external API versions.
- Anonymization policy for non-production environments.
- SIEM integration requirements.
- SSO requirements beyond Microsoft.
- Segmentation rules by site, plant, or project.

## Recommended Next Steps

1. Create an ADR for raw payload retention and storage.
2. Define an operations RACI matrix.
3. Define telemetry processing SLOs.
4. Add automatic monitoring for `pending` events and failed jobs.
5. Run load tests with expected uplink volume.
6. Formalize field calibration procedure.
7. Review customer cybersecurity requirements before pilot execution.
