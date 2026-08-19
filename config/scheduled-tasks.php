<?php

declare(strict_types=1);

return [
    'evaluate-alerts' => ['label' => 'Evaluación de alertas', 'description' => 'Detecta condiciones operativas y genera alertas.', 'interval_minutes' => 10, 'command' => 'loratrack:evaluate-alerts'],
    'process-meraki-webhooks' => ['label' => 'Recepción Meraki', 'description' => 'Normaliza los lotes recibidos desde Meraki.', 'interval_minutes' => 1, 'command' => 'loratrack:process-meraki-webhooks', 'arguments' => ['--limit' => (int) env('MERAKI_WEBHOOK_BATCH_LIMIT', 25)]],
    'process-meraki-observations' => ['label' => 'Observaciones Meraki', 'description' => 'Procesa posiciones, dispositivos y señales pendientes, incluidos reintentos.', 'interval_minutes' => 1, 'command' => 'loratrack:process-meraki-observations', 'arguments' => ['--limit' => (int) env('MERAKI_OBSERVATION_LIMIT', 1000)]],
    'process-tti-uplinks' => ['label' => 'Uplinks TTI', 'description' => 'Procesa uplinks LoRaWAN pendientes, incluidos reintentos.', 'interval_minutes' => 1, 'command' => 'loratrack:process-tti-uplinks', 'arguments' => ['--limit' => (int) env('TTI_PROCESS_LIMIT', 500)]],
    'process-mqtt-telemetry' => ['label' => 'Telemetría MQTT', 'description' => 'Procesa mensajes normalizados desde MQTT, incluidos reintentos.', 'interval_minutes' => 1, 'command' => 'loratrack:process-mqtt-telemetry', 'arguments' => ['--limit' => (int) env('MQTT_PROCESS_LIMIT', 500)]],
    'process-catalog-syncs' => ['label' => 'Sincronizaciones de catálogo', 'description' => 'Ejecuta importaciones de productos solicitadas.', 'interval_minutes' => 1, 'command' => 'loratrack:process-catalog-syncs'],
    'sync-telemetry-counters' => ['label' => 'Contadores de telemetría', 'description' => 'Actualiza los indicadores visibles en conectores.', 'interval_minutes' => 5, 'command' => 'loratrack:sync-telemetry-counters'],
    'manage-telemetry-storage' => ['label' => 'Retención y almacenamiento', 'description' => 'Aplica por tenant la retención estricta de telemetría, posiciones, logs y bandejas de entrada.', 'interval_minutes' => 10, 'command' => 'loratrack:manage-telemetry-storage'],
];
