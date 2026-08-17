<?php

declare(strict_types=1);

return [
    'evaluate-alerts' => ['label' => 'Evaluación de alertas', 'description' => 'Detecta condiciones operativas y genera alertas.', 'frequency' => 'Cada 10 minutos', 'command' => 'loratrack:evaluate-alerts'],
    'process-meraki-webhooks' => ['label' => 'Recepción Meraki', 'description' => 'Normaliza los lotes recibidos desde Meraki.', 'frequency' => 'Cada minuto', 'command' => 'loratrack:process-meraki-webhooks', 'arguments' => ['--limit' => 3]],
    'process-meraki-observations' => ['label' => 'Observaciones Meraki', 'description' => 'Procesa posiciones, dispositivos y señales pendientes.', 'frequency' => 'Cada minuto', 'command' => 'loratrack:process-meraki-observations'],
    'process-tti-uplinks' => ['label' => 'Uplinks TTI', 'description' => 'Procesa uplinks LoRaWAN pendientes.', 'frequency' => 'Cada minuto', 'command' => 'loratrack:process-tti-uplinks'],
    'process-mqtt-telemetry' => ['label' => 'Telemetría MQTT', 'description' => 'Procesa mensajes normalizados desde MQTT.', 'frequency' => 'Cada minuto', 'command' => 'loratrack:process-mqtt-telemetry'],
    'process-catalog-syncs' => ['label' => 'Sincronizaciones de catálogo', 'description' => 'Ejecuta importaciones de productos solicitadas.', 'frequency' => 'Cada minuto', 'command' => 'loratrack:process-catalog-syncs'],
    'sync-telemetry-counters' => ['label' => 'Contadores de telemetría', 'description' => 'Actualiza los indicadores visibles en conectores.', 'frequency' => 'Cada 5 minutos', 'command' => 'loratrack:sync-telemetry-counters'],
    'manage-telemetry-storage' => ['label' => 'Gestión de almacenamiento', 'description' => 'Controla límites y mantenimiento de telemetría.', 'frequency' => 'Cada hora', 'command' => 'loratrack:manage-telemetry-storage'],
    'prune-meraki-history' => ['label' => 'Retención histórica Meraki', 'description' => 'Elimina historial vencido de forma controlada.', 'frequency' => 'Cada hora', 'command' => 'loratrack:prune-meraki-history'],
];
