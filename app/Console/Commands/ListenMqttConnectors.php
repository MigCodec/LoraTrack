<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Models\Connector;
use App\Models\TelemetryEvent;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Throwable;

class ListenMqttConnectors extends Command
{
    protected $signature = 'loratrack:mqtt-listen {connector?}';

    protected $description = 'Escucha un conector MQTT activo y procesa su telemetría';

    public function handle(): int
    {
        $query = Connector::query()->where('provider', ConnectorProvider::Mqtt)->where('status', ConnectorStatus::Active);
        if ($this->argument('connector')) {
            $query->whereKey($this->argument('connector'));
        }
        $connector = $query->first();
        if (! $connector) {
            $this->error('No existe un conector MQTT activo.');

            return self::FAILURE;
        }

        $config = $connector->configuration;
        $secrets = $connector->credentials;
        $context = app(OrganizationContext::class);
        $context->set($connector->organization);
        $client = new MqttClient($config['host'], (int) $config['port'], 'loratrack-'.$connector->id, MqttClient::MQTT_3_1_1);

        try {
            $connector->logActivity('listener_starting', "Iniciando listener para {$config['host']}:{$config['port']}.");
            $settings = (new ConnectionSettings)->setUsername($secrets['username'] ?? null)->setPassword($secrets['password'] ?? null)->setUseTls((bool) ($config['tls'] ?? true))->setConnectTimeout(15)->setKeepAliveInterval(30);
            $client->connect($settings, true);
            $connector->forceFill(['last_activity_at' => now(), 'last_error' => null])->save();
            $connector->logActivity('connected', "Conectado al broker. Suscripción: {$config['topic']}.");
            $client->subscribe($config['topic'], function (string $topic, string $message) use ($connector, $config): void {
                $decoded = json_decode($message, true);
                if (! is_array($decoded)) {
                    $connector->logActivity('message_rejected', "Mensaje descartado en {$topic}: no es JSON válido.", 'warning', ['topic' => $topic, 'bytes' => strlen($message)]);

                    return;
                }
                $received = now();
                $event = TelemetryEvent::query()->firstOrCreate(
                    ['connector_id' => $connector->id, 'external_event_id' => hash('sha256', $topic.'|'.$message)],
                    ['event_type' => 'mqtt', 'observed_at' => $received, 'received_at' => $received, 'raw_payload' => ['end_device_ids' => ['device_id' => $config['receiver_identifier'] ?? 'mqtt-receiver'], 'uplink_message' => ['decoded_payload' => $decoded]], 'processing_status' => 'pending'],
                );
                $connector->forceFill(['last_activity_at' => $received, 'last_error' => null])->save();
                if ($event->wasRecentlyCreated) {
                    $connector->logActivity('message_received', "Mensaje recibido en {$topic} y pendiente de procesamiento programado.", 'info', ['topic' => $topic, 'event_id' => $event->id, 'bytes' => strlen($message)]);
                } else {
                    $connector->logActivity('duplicate_ignored', "Mensaje duplicado ignorado en {$topic}.", 'warning', ['topic' => $topic, 'event_id' => $event->id]);
                }
            }, 0);
            $this->info("Escuchando {$config['topic']} en {$config['host']}...");
            $client->loop(true);
        } catch (Throwable $exception) {
            $connector->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 1000)])->save();
            $connector->logActivity('listener_error', mb_substr($exception->getMessage(), 0, 1000), 'error');
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $context->set(null);
        }

        return self::SUCCESS;
    }
}
