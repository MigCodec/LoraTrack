<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Device;
use App\Models\TelemetryEvent;
use App\Positioning\BleObservationExtractor;
use App\Positioning\PayloadProfileDecoder;
use App\Positioning\TelemetryPositioningService;
use App\Telemetry\AssetLastSeenUpdater;
use App\Tenancy\OrganizationContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessTtiUplink implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('telemetry:'.$this->telemetryEventId))->releaseAfter(5)->expireAfter(120)];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function __construct(public readonly string $telemetryEventId) {}

    public function handle(BleObservationExtractor $extractor, PayloadProfileDecoder $profileDecoder, TelemetryPositioningService $positioning, AssetLastSeenUpdater $lastSeenUpdater): void
    {
        $event = TelemetryEvent::query()->findOrFail($this->telemetryEventId);
        $context = app(OrganizationContext::class);
        $context->set($event->organization);

        try {
            if ($event->processing_status === 'processed') {
                return;
            }
            $payload = $event->raw_payload;
            $deviceIdentifier = (string) (Arr::get($payload, 'end_device_ids.dev_eui') ?: Arr::get($payload, 'end_device_ids.device_id'));
            $device = $deviceIdentifier !== '' ? Device::query()->firstOrNew(['identifier' => $deviceIdentifier]) : null;
            if ($device) {
                $device->fill([
                    'name' => $device->exists ? $device->name : (string) (Arr::get($payload, 'end_device_ids.device_id') ?: $deviceIdentifier),
                    'type' => $device->exists ? $device->type : 'lorawan_tracker',
                    'status' => 'active',
                    'last_seen_at' => $event->observed_at ?? now(),
                ])->save();
            }

            $profileResult = $profileDecoder->decode($payload, $event->connector);
            $decoded = $profileResult['decoded'];
            $receiverIdentifier = $profileResult['receiver_identifier'] ?: $deviceIdentifier;

            $event->forceFill([
                'device_id' => $device?->id,
                'normalized_payload' => [
                    'device_identifier' => $deviceIdentifier,
                    'frame_counter' => Arr::get($payload, 'uplink_message.f_cnt'),
                    'frame_port' => Arr::get($payload, 'uplink_message.f_port'),
                    'decoded' => $decoded,
                    'decoder_profile_id' => $profileResult['profile']?->id,
                    'decoder_profile_name' => $profileResult['profile']?->name,
                    'decoder_product_ids' => $profileResult['profile']?->products()->pluck('products.id')->all() ?? [],
                    'raw_base64' => Arr::get($payload, 'uplink_message.frm_payload'),
                    'rx_metadata' => Arr::get($payload, 'uplink_message.rx_metadata', []),
                    'settings' => Arr::get($payload, 'uplink_message.settings', []),
                ],
            ])->save();

            foreach ($extractor->extract($decoded) as $observation) {
                $event->signalObservations()->updateOrCreate(
                    ['transmitter_mac' => $observation['mac']],
                    [
                        'receiver_identifier' => BleObservationExtractor::normalizeMac($receiverIdentifier),
                        'rssi' => $observation['rssi'],
                        'observed_at' => $event->observed_at ?? $event->received_at,
                        'metadata' => $observation['metadata'],
                    ],
                );
            }

            $lastSeenUpdater->updateFromUplink($event->load('signalObservations'));
            $positioning->positionEvent($event->fresh());
            $event->forceFill([
                'processing_status' => 'processed',
                'processed_at' => now(),
                'processing_error' => null,
            ])->save();
            $event->connector()->update(['last_success_at' => now(), 'last_error' => null]);
        } catch (Throwable $exception) {
            $event->forceFill([
                'processing_status' => 'failed',
                'processing_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();
            $event->connector()->update(['last_error' => mb_substr($exception->getMessage(), 0, 1000)]);
            Log::error('Falló el procesamiento de telemetría.', ['telemetry_event_id' => $event->id, 'connector_id' => $event->connector_id, 'exception' => $exception::class]);
            throw $exception;
        } finally {
            $context->set(null);
        }
    }
}
