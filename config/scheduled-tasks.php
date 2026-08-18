<?php

declare(strict_types=1);

return [
    'evaluate-alerts' => ['label' => 'Evaluación de alertas', 'description' => 'Detecta condiciones operativas y genera alertas.', 'command' => 'loratrack:evaluate-alerts', 'default_interval' => 10, 'minimum_interval' => 5, 'maximum_interval' => 1440],
    'process-meraki-webhooks' => ['label' => 'Recepción Meraki', 'description' => 'Normaliza los lotes recibidos desde Meraki usando el límite de la organización.', 'command' => 'loratrack:process-meraki-webhooks', 'default_interval' => 1, 'minimum_interval' => 1, 'maximum_interval' => 1440],
    'process-meraki-observations' => ['label' => 'Observaciones Meraki', 'description' => 'Procesa posiciones, dispositivos y señales pendientes.', 'command' => 'loratrack:process-meraki-observations', 'default_interval' => 1, 'minimum_interval' => 1, 'maximum_interval' => 1440],
    'process-tti-uplinks' => ['label' => 'Uplinks TTI', 'description' => 'Procesa uplinks LoRaWAN pendientes.', 'command' => 'loratrack:process-tti-uplinks', 'default_interval' => 1, 'minimum_interval' => 1, 'maximum_interval' => 1440],
    'process-mqtt-telemetry' => ['label' => 'Telemetría MQTT', 'description' => 'Procesa mensajes normalizados desde MQTT.', 'command' => 'loratrack:process-mqtt-telemetry', 'default_interval' => 1, 'minimum_interval' => 1, 'maximum_interval' => 1440],
    'process-catalog-syncs' => ['label' => 'Sincronizaciones de catálogo', 'description' => 'Ejecuta importaciones de productos solicitadas.', 'command' => 'loratrack:process-catalog-syncs', 'default_interval' => 1, 'minimum_interval' => 1, 'maximum_interval' => 1440],
    'sync-telemetry-counters' => ['label' => 'Contadores de telemetría', 'description' => 'Actualiza los indicadores visibles en conectores.', 'command' => 'loratrack:sync-telemetry-counters', 'default_interval' => 5, 'minimum_interval' => 1, 'maximum_interval' => 1440],
    'manage-telemetry-storage' => ['label' => 'Gestión de almacenamiento', 'description' => 'Controla límites y mantenimiento de telemetría.', 'command' => 'loratrack:manage-telemetry-storage', 'default_interval' => 60, 'minimum_interval' => 15, 'maximum_interval' => 10080],
    'prune-meraki-history' => ['label' => 'Retención histórica Meraki', 'description' => 'Elimina historial vencido usando el máximo de mantenimiento de la organización.', 'command' => 'loratrack:prune-meraki-history-incremental', 'arguments' => ['--batch-size' => 100], 'default_interval' => 60, 'minimum_interval' => 5, 'maximum_interval' => 10080],
];
