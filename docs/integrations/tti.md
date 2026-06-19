# Integración TTI

LoraTrack recibe uplinks desde The Things Stack mediante HTTPS. TTI conserva la responsabilidad de red LoRaWAN, gateways, dispositivos y entrega.

## Contrato de entrada

- Endpoint: `POST /api/v1/ingest/tti/{connector}`
- Autenticación: `Authorization: Bearer <token>`
- Respuesta aceptada: HTTP 202
- Identidad idempotente: hash estable de dispositivo, sesión, frame counter y fecha TTI

Se conservan `raw_payload`, hora de recepción y la versión normalizada. El job extrae `decoded_payload`, `frm_payload`, `rx_metadata`, frame counter, puerto y datos del dispositivo.

Referencia: <https://www.thethingsindustries.com/docs/integrations/webhooks/>
