<?php

declare(strict_types=1);

namespace App\Connectors\Meraki;

use App\Jobs\ProcessMerakiLocationObservation;
use App\Models\Connector;
use App\Models\TelemetryEvent;
use App\Positioning\ZoneClassifier;
use App\Support\TelemetryTimestamp;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ProcessMerakiWebhookInline
{
    public function __construct(
        private readonly MerakiPayloadNormalizer $normalizer,
        private readonly MerakiAccessPointRegistrar $accessPoints,
        private readonly MerakiClientDeviceRegistrar $clients,
        private readonly ZoneClassifier $zones,
    ) {}

    /** @param array<string, mixed> $payload
     *  @return array{accepted: int, duplicates: int}
     */
    public function process(Connector $connector, array $payload, int $majorVersion, Carbon $receivedAt): array
    {
        $organization = $connector->organization;
        if (! $organization) {
            throw new \RuntimeException('La organizacion del conector Meraki no existe.');
        }

        $context = app(OrganizationContext::class);
        $context->set($organization);

        try {
            $records = $this->normalizer->records($payload, $majorVersion);
            if ($records === []) {
                throw ValidationException::withMessages(['data.observations' => 'El POST no contiene observaciones Meraki validas.']);
            }

            $configuredNetwork = trim((string) ($connector->configuration['network_id'] ?? ''));
            if ($configuredNetwork !== '' && ! collect($records)->every(
                fn (array $record): bool => ($record['network_id'] ?? '') === $configuredNetwork,
            )) {
                throw ValidationException::withMessages(['network_id' => 'El networkId no corresponde al configurado.']);
            }

            $records = $this->registerAccessPointInventory($records, $receivedAt);
            $processor = new ProcessMerakiLocationObservation('inline');
            $accepted = 0;
            $duplicates = 0;

            foreach ($records as $record) {
                $externalEventId = $this->externalEventId($record);
                $observedAt = filled($record['observed_at'] ?? null)
                    ? TelemetryTimestamp::parseProviderTime($record['observed_at'])
                    : null;
                $event = TelemetryEvent::query()->firstOrCreate([
                    'connector_id' => $connector->id,
                    'external_event_id' => $externalEventId,
                ], [
                    'organization_id' => $connector->organization_id,
                    'event_type' => 'meraki_location',
                    'observed_at' => $observedAt,
                    'received_at' => $receivedAt,
                    'raw_payload' => $record,
                    'normalized_payload' => null,
                    'processing_status' => 'processing',
                ]);

                if ($event?->processing_status === 'processed') {
                    $duplicates++;
                    continue;
                }

                $event->forceFill([
                    'observed_at' => $observedAt,
                    'raw_payload' => $record,
                    'normalized_payload' => null,
                    'processing_status' => 'processing',
                    'processing_error' => null,
                ])->save();
                $event->loadMissing(['organization', 'connector']);
                $processor->process($event, $this->zones, $this->accessPoints, $this->clients, false);
                $accepted++;
            }

            $connector->forceFill(['last_activity_at' => now(), 'last_error' => null])->save();
            $connector->logActivity('meraki_payload_processed_inline', 'POST Meraki procesado completamente dentro de la peticion.', 'info', [
                'accepted' => $accepted,
                'duplicates' => $duplicates,
                'version' => $payload['version'] ?? null,
            ]);

            return ['accepted' => $accepted, 'duplicates' => $duplicates];
        } finally {
            $context->set(null);
        }
    }

    /** @param list<array<string, mixed>> $records
     *  @return list<array<string, mixed>>
     */
    private function registerAccessPointInventory(array $records, Carbon $receivedAt): array
    {
        $seen = [];
        foreach ($records as &$record) {
            foreach (($record['reporting_aps'] ?? []) as $accessPoint) {
                if (! is_array($accessPoint)) {
                    continue;
                }
                $identifier = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($accessPoint['apMac'] ?? '')) ?? '');
                if ($identifier === '' || isset($seen[$identifier])) {
                    continue;
                }
                $seen[$identifier] = true;
                $this->accessPoints->register($accessPoint, $receivedAt, (string) ($record['network_id'] ?? ''));
            }
            $record['reporting_aps'] = [];
        }
        unset($record);

        return $records;
    }

    /** @param array<string, mixed> $record */
    private function externalEventId(array $record): string
    {
        return hash('sha256', implode('|', [
            $record['version'], $record['type'], $record['network_id'], $record['client_mac'],
            $record['observed_at'], $record['external_floor_plan_id'], $record['x'], $record['y'],
        ]));
    }
}
