<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Connectors\Meraki\MerakiAccessPointRegistrar;
use App\Enums\ConnectorProvider;
use App\Models\TelemetryEvent;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BackfillMerakiAccessPoints extends Command
{
    protected $signature = 'loratrack:backfill-meraki-access-points
        {--dry-run : Contar AP detectables sin crear ni actualizar dispositivos}
        {--limit=10000 : Maximo de eventos Meraki a revisar}';

    protected $description = 'Reconstruye el inventario de AP Meraki desde payloads normalizados ya recibidos.';

    public function handle(MerakiAccessPointRegistrar $registrar): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100000],
        ]);
        if ($limit === false) {
            $this->error('--limit debe ser un entero entre 1 y 100000.');

            return self::FAILURE;
        }

        $context = app(OrganizationContext::class);
        $events = TelemetryEvent::query()
            ->withoutGlobalScopes()
            ->with('connector.organization')
            ->where('event_type', 'meraki_location')
            ->latest('received_at')
            ->limit($limit)
            ->get();

        $seen = [];
        $registered = 0;
        $detectable = 0;

        foreach ($events as $event) {
            if ($event->connector?->provider !== ConnectorProvider::MerakiLocation || ! $event->organization?->active) {
                continue;
            }

            $payload = $event->normalized_payload ?: $event->raw_payload ?: [];
            $readings = collect($payload['reporting_aps'] ?? [])
                ->merge($payload['rssi_records'] ?? [])
                ->filter(fn (mixed $reading): bool => is_array($reading) && is_string($reading['apMac'] ?? null))
                ->unique(fn (array $reading): string => mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $reading['apMac']) ?? ''))
                ->values();

            if ($readings->isEmpty()) {
                continue;
            }

            $context->set($event->organization);
            try {
                foreach ($readings as $reading) {
                    $key = $event->organization_id.'|'.mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $reading['apMac']) ?? '');
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $detectable++;

                    if (! $this->option('dry-run')) {
                        $device = $registrar->register(
                            $reading,
                            $event->observed_at ?? $event->received_at ?? Carbon::now(),
                            (string) ($payload['network_id'] ?? ''),
                        );
                        if ($device?->type === 'scanner') {
                            $registered++;
                        }
                    }
                }
            } finally {
                $context->set(null);
            }
        }

        $this->info("AP Meraki detectables en eventos revisados: {$detectable}.");
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $this->info("AP Meraki creados o actualizados como scanner: {$registered}.");

        return self::SUCCESS;
    }
}
