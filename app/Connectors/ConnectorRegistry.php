<?php

declare(strict_types=1);

namespace App\Connectors;

use App\Enums\ConnectorKind;
use App\Enums\ConnectorProvider;
use InvalidArgumentException;

class ConnectorRegistry
{
    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return [
            ConnectorProvider::TtiWebhook->value => [
                'provider' => ConnectorProvider::TtiWebhook,
                'kind' => ConnectorKind::Telemetry,
                'name' => 'TTI Webhook',
                'description' => 'Recibe uplinks JSON enviados por The Things Stack mediante HTTPS.',
                'configuration' => [
                    'receiver_identifier' => ['label' => 'Identificador del receptor', 'type' => 'text', 'required' => false],
                ],
                'credentials' => [
                    'webhook_token' => ['label' => 'Token del webhook', 'type' => 'password', 'required' => true, 'min' => 24],
                ],
            ],
            ConnectorProvider::Mqtt->value => [
                'provider' => ConnectorProvider::Mqtt,
                'kind' => ConnectorKind::Telemetry,
                'name' => 'MQTT',
                'description' => 'Consume telemetría desde un broker MQTT configurable.',
                'configuration' => [
                    'host' => ['label' => 'Host', 'type' => 'text', 'required' => true],
                    'port' => ['label' => 'Puerto', 'type' => 'number', 'required' => true, 'default' => 8883],
                    'tls' => ['label' => 'Usar TLS', 'type' => 'checkbox', 'required' => false, 'default' => true],
                    'topic' => ['label' => 'Topic de uplinks', 'type' => 'text', 'required' => true],
                    'decoder_profile' => ['label' => 'Perfil de decoder', 'type' => 'text', 'required' => false],
                ],
                'credentials' => [
                    'username' => ['label' => 'Usuario', 'type' => 'text', 'required' => false],
                    'password' => ['label' => 'Contraseña / API key', 'type' => 'password', 'required' => false],
                ],
            ],
            ConnectorProvider::SapS4Hana->value => [
                'provider' => ConnectorProvider::SapS4Hana,
                'kind' => ConnectorKind::Catalog,
                'name' => 'SAP S/4HANA',
                'description' => 'Sincroniza Product Master mediante OData.',
                'configuration' => [
                    'base_url' => ['label' => 'URL base de SAP', 'type' => 'url', 'required' => true],
                    'api_path' => ['label' => 'Ruta Product Master', 'type' => 'text', 'required' => true, 'default' => '/sap/opu/odata/sap/API_PRODUCT_SRV'],
                    'auth_type' => ['label' => 'Autenticación', 'type' => 'select', 'required' => true, 'default' => 'basic', 'options' => ['basic' => 'Usuario y contraseña', 'bearer' => 'Bearer token']],
                ],
                'credentials' => [
                    'username' => ['label' => 'Usuario SAP', 'type' => 'text', 'required' => false],
                    'password' => ['label' => 'Contraseña SAP', 'type' => 'password', 'required' => false],
                    'token' => ['label' => 'Bearer token', 'type' => 'password', 'required' => false],
                ],
            ],
            ConnectorProvider::BusinessCentral->value => [
                'provider' => ConnectorProvider::BusinessCentral, 'kind' => ConnectorKind::Catalog, 'name' => 'Business Central', 'description' => 'Microsoft Dynamics 365 Business Central API v2.0.',
                'configuration' => ['tenant_id' => ['label' => 'Tenant ID', 'type' => 'text', 'required' => true], 'environment' => ['label' => 'Environment', 'type' => 'text', 'required' => true, 'default' => 'Production'], 'company_id' => ['label' => 'Company ID', 'type' => 'text', 'required' => true], 'base_url' => ['label' => 'URL API', 'type' => 'url', 'required' => true, 'default' => 'https://api.businesscentral.dynamics.com/v2.0']],
                'credentials' => ['client_id' => ['label' => 'Client ID', 'type' => 'text', 'required' => true], 'client_secret' => ['label' => 'Client secret', 'type' => 'password', 'required' => true]],
            ],
            ConnectorProvider::Shopify->value => [
                'provider' => ConnectorProvider::Shopify, 'kind' => ConnectorKind::Catalog, 'name' => 'Shopify', 'description' => 'Catálogo y variantes mediante Admin GraphQL API.',
                'configuration' => ['shop_domain' => ['label' => 'Dominio myshopify.com', 'type' => 'text', 'required' => true], 'api_version' => ['label' => 'Versión API', 'type' => 'text', 'required' => true, 'default' => '2026-04']],
                'credentials' => ['access_token' => ['label' => 'Admin API access token', 'type' => 'password', 'required' => true]],
            ],
            ConnectorProvider::Odoo->value => [
                'provider' => ConnectorProvider::Odoo, 'kind' => ConnectorKind::Catalog, 'name' => 'Odoo', 'description' => 'Catálogo mediante External JSON-2 API.',
                'configuration' => ['base_url' => ['label' => 'URL de Odoo', 'type' => 'url', 'required' => true], 'database' => ['label' => 'Base de datos', 'type' => 'text', 'required' => true]],
                'credentials' => ['api_key' => ['label' => 'API key', 'type' => 'password', 'required' => true]],
            ],
            ConnectorProvider::Csv->value => [
                'provider' => ConnectorProvider::Csv,
                'kind' => ConnectorKind::Catalog,
                'name' => 'CSV',
                'description' => 'Importación manual usando una plantilla controlada.',
                'configuration' => [],
                'credentials' => [],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function get(ConnectorProvider|string $provider): array
    {
        $key = $provider instanceof ConnectorProvider ? $provider->value : $provider;

        return $this->all()[$key] ?? throw new InvalidArgumentException("Unknown connector provider [$key].");
    }

    /** @return array<string, mixed> */
    private function catalogDefinition(ConnectorProvider $provider, string $name, string $description): array
    {
        return [
            'provider' => $provider,
            'kind' => ConnectorKind::Catalog,
            'name' => $name,
            'description' => $description,
            'configuration' => [
                'base_url' => ['label' => 'URL base', 'type' => 'url', 'required' => true],
            ],
            'credentials' => [
                'client_id' => ['label' => 'Client ID', 'type' => 'text', 'required' => true],
                'client_secret' => ['label' => 'Client secret', 'type' => 'password', 'required' => true],
            ],
        ];
    }
}
