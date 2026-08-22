<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Connectors\Meraki\MerakiAccessPointRegistrar;
use App\Enums\ConnectorProvider;
use App\Models\SignalObservation;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BackfillMerakiAccessPoints extends Command
{
    protected $signature = 'loratrack:backfill-meraki-access-points
        {--dry-run : Contar AP detectables sin crear ni actualizar dispositivos}
        {--limit=10000 : Maximo de observaciones de senal Meraki a revisar}';

    protected $description = 'Reconstruye el inventario de AP Meraki desde observaciones de señal normalizadas.';

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
        $observations = SignalObservation::query()
            ->withoutGlobalScopes()
            ->with('telemetryEvent.connector.organization')
            ->whereHas('telemetryEvent', fn ($query) => $query->where('event_type', 'meraki_location'))
            ->whereNotNull('receiver_identifier')
            ->latest('observed_at')
            ->limit($limit)
            ->get();

        $seen = [];
        $registered = 0;
        $detectable = 0;

        foreach ($observations as $observation) {
            $event = $observation->telemetryEvent;
            $organization = $event?->connector?->organization;
            if ($event?->connector?->provider !== ConnectorProvider::MerakiLocation || ! $organization?->active) {
                continue;
            }
            $identifier = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $observation->receiver_identifier) ?? '');
            $key = $event->organization_id.'|'.$identifier;
            if ($identifier === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $detectable++;

            $context->set($organization);
            try {
                if (! $this->option('dry-run')) {
                    $device = $registrar->register(
                        ['apMac' => $identifier, 'rssi' => $observation->rssi],
                        $observation->observed_at ?? $event->observed_at ?? $event->received_at ?? Carbon::now(),
                        '',
                    );
                    if ($device?->type === 'scanner') {
                        $registered++;
                    }
                }
            } finally {
                $context->set(null);
            }
        }

        $this->info("AP Meraki detectables en observaciones revisadas: {$detectable}.");
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $this->info("AP Meraki creados o actualizados como scanner: {$registered}.");

        return self::SUCCESS;
    }
}
