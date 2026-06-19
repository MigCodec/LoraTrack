<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Connectors\BusinessCentral\BusinessCentralSynchronizer;
use App\Connectors\Odoo\OdooSynchronizer;
use App\Connectors\Sap\SapProductSynchronizer;
use App\Connectors\Shopify\ShopifySynchronizer;
use App\Enums\ConnectorProvider;
use App\Models\Connector;
use App\Tenancy\OrganizationContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncCatalogConnector implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('catalog:'.$this->connectorId))->releaseAfter(30)->expireAfter(1800)];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(public readonly string $connectorId) {}

    public function handle(SapProductSynchronizer $sap, BusinessCentralSynchronizer $businessCentral, ShopifySynchronizer $shopify, OdooSynchronizer $odoo): void
    {
        $connector = Connector::query()->findOrFail($this->connectorId);
        $context = app(OrganizationContext::class);
        $context->set($connector->organization);

        try {
            match ($connector->provider) {
                ConnectorProvider::SapS4Hana => $sap->sync($connector),
                ConnectorProvider::BusinessCentral => $businessCentral->sync($connector),
                ConnectorProvider::Shopify => $shopify->sync($connector),
                ConnectorProvider::Odoo => $odoo->sync($connector),
                default => null,
            };
            $connector->forceFill(['status' => 'active', 'last_activity_at' => now(), 'last_success_at' => now(), 'last_error' => null])->save();
        } catch (Throwable $exception) {
            $connector->forceFill([
                'status' => 'error',
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();
            Log::error('Falló la sincronización de catálogo.', ['connector_id' => $connector->id, 'provider' => $connector->provider->value, 'exception' => $exception::class]);
            throw $exception;
        } finally {
            $context->set(null);
        }
    }
}
