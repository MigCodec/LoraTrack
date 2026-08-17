<?php

declare(strict_types=1);

return [
    'evaluate-alerts' => ['label' => 'Evaluación de alertas', 'command' => 'loratrack:evaluate-alerts'],
    'process-meraki-webhooks' => ['label' => 'Recepción Meraki', 'command' => 'loratrack:process-meraki-webhooks', 'arguments' => ['--limit' => 3]],
    'process-meraki-observations' => ['label' => 'Observaciones Meraki', 'command' => 'loratrack:process-meraki-observations'],
    'process-tti-uplinks' => ['label' => 'Uplinks TTI', 'command' => 'loratrack:process-tti-uplinks'],
    'process-mqtt-telemetry' => ['label' => 'Telemetría MQTT', 'command' => 'loratrack:process-mqtt-telemetry'],
    'process-catalog-syncs' => ['label' => 'Sincronizaciones de catálogo', 'command' => 'loratrack:process-catalog-syncs'],
    'sync-telemetry-counters' => ['label' => 'Contadores de telemetría', 'command' => 'loratrack:sync-telemetry-counters'],
    'manage-telemetry-storage' => ['label' => 'Gestión de almacenamiento', 'command' => 'loratrack:manage-telemetry-storage'],
    'prune-meraki-history' => ['label' => 'Retención histórica Meraki', 'command' => 'loratrack:prune-meraki-history'],
];
