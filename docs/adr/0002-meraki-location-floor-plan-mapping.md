# ADR 0002: Integración de Cisco Meraki Location API

## Contexto

Meraki Scanning/Location API v2.1 y v3.x entregan observaciones WiFi/BLE, RSSI y posiciones calculadas. Sus identificadores de planos y su sistema de coordenadas no pertenecen al dominio interno de LoraTrack.

## Decisión

- Implementar Meraki como proveedor de telemetría independiente.
- Seleccionar el major de contrato por instancia de conector y aceptar versiones menores compatibles.
- Autenticar cada POST mediante el shared secret del payload y exponer el GET de validación exigido por Meraki.
- Persistir una observación auditable por cliente/posición y procesarla de forma idempotente mediante cola.
- Registrar dispositivos por MAC normalizada y generar posiciones sólo cuando exista una vinculación temporal con un activo.
- Mantener una tabla de mapeo por conector entre el identificador de plano Meraki y el plano LoraTrack.
- Convertir por defecto el eje Y de origen inferior de Meraki al origen superior del editor web.
- Conservar `variance`/`unc` como precisión reportada por el proveedor, sin presentarla como precisión calculada por LoraTrack.

## Alternativas consideradas

- Asociar planos por nombre: descartado por ambigüedad y cambios de nombre.
- Guardar el ID Meraki directamente en `floor_plans`: descartado porque un plano puede relacionarse con varios conectores.
- Recalcular siempre la posición desde RSSI: descartado porque perdería la estimación y precisión proporcionadas por Meraki.

## Consecuencias

Cada plano Meraki debe mapearse antes de mostrar coordenadas locales. Sin mapeo todavía se registran la MAC, las observaciones y las coordenadas geográficas disponibles.
