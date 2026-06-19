<?php

declare(strict_types=1);

namespace App\Connectors;

use App\Enums\ConnectorProvider;
use App\Models\Connector;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ConnectorConnectionTester
{
    public function test(Connector $connector): string
    {
        return match ($connector->provider) {
            ConnectorProvider::TtiWebhook => 'Configuración válida. El endpoint está listo para recibir eventos.',
            ConnectorProvider::SapS4Hana => $this->testSap($connector),
            ConnectorProvider::Mqtt => 'Configuración guardada. La conexión MQTT se validará al iniciar el consumidor.',
            default => 'Configuración válida. La prueba remota se habilitará con el sincronizador del proveedor.',
        };
    }

    private function testSap(Connector $connector): string
    {
        $configuration = $connector->configuration ?? [];
        $url = rtrim((string) ($configuration['base_url'] ?? ''), '/').'/'.trim((string) ($configuration['api_path'] ?? ''), '/').'/$metadata';

        $response = $this->authenticatedRequest($connector)->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('SAP respondió HTTP '.$response->status().'.');
        }

        return 'Conexión SAP verificada correctamente.';
    }

    public function authenticatedRequest(Connector $connector): PendingRequest
    {
        $request = Http::acceptJson()->timeout(15)->retry(2, 250, throw: false);
        $credentials = $connector->credentials ?? [];

        return ($connector->configuration['auth_type'] ?? 'basic') === 'bearer'
            ? $request->withToken((string) ($credentials['token'] ?? ''))
            : $request->withBasicAuth((string) ($credentials['username'] ?? ''), (string) ($credentials['password'] ?? ''));
    }
}
