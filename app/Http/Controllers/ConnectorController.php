<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Connectors\CatalogProductImporter;
use App\Connectors\ConnectorConnectionTester;
use App\Connectors\ConnectorRegistry;
use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Http\Requests\StoreConnectorRequest;
use App\Jobs\SyncCatalogConnector;
use App\Models\Connector;
use App\Models\FloorPlan;
use App\Models\TelemetryEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ConnectorController extends Controller
{
    public function index(ConnectorRegistry $registry): View
    {
        return view('connectors.index', [
            'definitions' => collect($registry->all())->groupBy(fn (array $item) => $item['kind']->value),
            'connectors' => Connector::query()->withCount([
                'telemetryEvents',
                'telemetryEvents as processed_events_count' => fn ($query) => $query->where('processing_status', 'processed'),
                'telemetryEvents as failed_events_count' => fn ($query) => $query->where('processing_status', 'failed'),
            ])->latest()->get(),
        ]);
    }

    public function show(Connector $connector, ConnectorRegistry $registry): View
    {
        $connector->loadCount([
            'telemetryEvents',
            'telemetryEvents as pending_events_count' => fn ($query) => $query->where('processing_status', 'pending'),
            'telemetryEvents as processed_events_count' => fn ($query) => $query->where('processing_status', 'processed'),
            'telemetryEvents as failed_events_count' => fn ($query) => $query->where('processing_status', 'failed'),
        ]);

        return view('connectors.show', [
            'connector' => $connector,
            'definition' => $registry->get($connector->provider),
            'events' => $connector->telemetryEvents()->with('device')->latest('received_at')->limit(100)->get(),
            'logs' => $connector->activityLogs()->latest()->limit(100)->get(),
            'merakiMappings' => $connector->provider === ConnectorProvider::MerakiLocation
                ? $connector->merakiFloorPlanMappings()->with('floorPlan.location')->get()
                : collect(),
            'floorPlans' => $connector->provider === ConnectorProvider::MerakiLocation
                ? FloorPlan::query()->with('location')->where('is_active', true)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function showEvent(Connector $connector, TelemetryEvent $telemetryEvent): View
    {
        abort_unless($telemetryEvent->connector_id === $connector->id, 404);

        return view('connectors.event', [
            'connector' => $connector,
            'event' => $telemetryEvent->load(['device', 'signalObservations']),
        ]);
    }

    public function create(string $provider, ConnectorRegistry $registry): View
    {
        abort_unless(ConnectorProvider::tryFrom($provider), 404);

        return view('connectors.create', ['definition' => $registry->get($provider)]);
    }

    public function store(StoreConnectorRequest $request, ConnectorRegistry $registry): RedirectResponse
    {
        $validated = $request->validated();
        $provider = ConnectorProvider::from($validated['provider']);
        $definition = $registry->get($provider);

        $connector = Connector::query()->create([
            'name' => $validated['name'],
            'provider' => $provider,
            'kind' => $definition['kind'],
            'status' => ConnectorStatus::Draft,
            'configuration' => $validated['configuration'] ?? [],
            'credentials' => $validated['credentials'] ?? [],
            'contract_version' => $provider === ConnectorProvider::MerakiLocation
                ? (string) ($validated['configuration']['api_version'] ?? '3')
                : '1',
        ]);
        $connector->logActivity('created', 'Conector creado como borrador. Debe probarse y activarse antes de operar.');

        return redirect()->route('connectors.index')->with('status', "Conector {$connector->name} creado.");
    }

    public function test(Connector $connector, ConnectorConnectionTester $tester): RedirectResponse
    {
        try {
            $message = $tester->test($connector);
            $connector->forceFill(['last_tested_at' => now(), 'last_error' => null])->save();
            $connector->logActivity('test_succeeded', $message);

            return back()->with('status', $message);
        } catch (Throwable $exception) {
            $connector->forceFill([
                'last_tested_at' => now(),
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();
            $connector->logActivity('test_failed', mb_substr($exception->getMessage(), 0, 1000), 'error');

            return back()->withErrors(['connector' => 'La prueba falló: '.$exception->getMessage()]);
        }
    }

    public function toggle(Connector $connector): RedirectResponse
    {
        $connector->update([
            'status' => $connector->status === ConnectorStatus::Active
                ? ConnectorStatus::Disabled
                : ConnectorStatus::Active,
        ]);
        $connector->logActivity('status_changed', 'Estado cambiado a '.$connector->fresh()->status->label().'.');

        return back()->with('status', 'Estado del conector actualizado.');
    }

    public function sync(Connector $connector): RedirectResponse
    {
        abort_unless($connector->kind->value === 'catalog', 422);
        SyncCatalogConnector::dispatch($connector->id);

        return back()->with('status', 'Sincronización enviada a la cola.');
    }

    public function importCsv(Request $request, Connector $connector, CatalogProductImporter $importer): RedirectResponse
    {
        abort_unless($connector->provider === ConnectorProvider::Csv, 422);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $handle = fopen($request->file('file')->getRealPath(), 'rb');
        $headers = array_map(fn ($value) => mb_strtolower(trim((string) $value)), fgetcsv($handle) ?: []);
        foreach (['sku', 'name'] as $required) {
            abort_unless(in_array($required, $headers, true), 422, "CSV requiere columna {$required}.");
        }
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }
            $item = array_combine($headers, $row);
            $count += (int) $importer->import($connector, ['external_id' => (string) (($item['external_id'] ?? '') ?: $item['sku']), 'sku' => (string) $item['sku'], 'name' => (string) $item['name'], 'description' => $item['description'] ?? null, 'base_unit' => $item['base_unit'] ?? null, 'status' => $item['status'] ?? 'active']);
        }
        fclose($handle);
        $connector->update(['last_success_at' => now(), 'last_error' => null]);

        return back()->with('status', "{$count} SKU importados desde CSV.");
    }

    public function destroy(Connector $connector): RedirectResponse
    {
        abort_if($connector->status === ConnectorStatus::Active, 422, 'Desactiva el conector antes de eliminarlo.');

        $name = $connector->name;
        $connector->delete();

        return redirect()->route('connectors.index')->with('status', "Conector {$name} eliminado.");
    }

    public function rotateWebhookToken(Connector $connector): RedirectResponse
    {
        abort_unless($connector->provider === ConnectorProvider::TtiWebhook, 422);

        $token = Str::random(64);
        $credentials = $connector->credentials ?? [];
        $credentials['webhook_token'] = $token;
        $connector->forceFill(['credentials' => $credentials])->save();
        $connector->logActivity('token_rotated', 'Token del webhook renovado. Las llamadas con el token anterior dejarán de funcionar.', 'warning');

        return back()->with('status', 'Token renovado. Cópialo ahora; solo se mostrará una vez.')
            ->with('new_webhook_token', $token);
    }

    public function updateMerakiCredentials(Request $request, Connector $connector): RedirectResponse
    {
        abort_unless($connector->provider === ConnectorProvider::MerakiLocation, 422);
        $validated = $request->validate([
            'validator' => ['nullable', 'string', 'min:8', 'max:255'],
            'shared_secret' => ['nullable', 'string', 'min:16', 'max:255'],
        ]);
        $validator = trim((string) ($validated['validator'] ?? ''));
        $sharedSecret = trim((string) ($validated['shared_secret'] ?? ''));
        if ($validator === '' && $sharedSecret === '') {
            throw ValidationException::withMessages([
                'validator' => 'Ingresa el validator de Meraki o un shared secret nuevo.',
            ]);
        }

        $credentials = $connector->credentials ?? [];
        $changed = [];
        if ($validator !== '') {
            $credentials['validator'] = $validator;
            $changed[] = 'validator';
        }
        if ($sharedSecret !== '') {
            $credentials['shared_secret'] = $sharedSecret;
            $changed[] = 'shared_secret';
        }

        $connector->forceFill(['credentials' => $credentials])->save();
        $connector->logActivity(
            'meraki_credentials_rotated',
            'Credenciales del receptor Meraki actualizadas. Los valores anteriores modificados dejaron de ser válidos.',
            'warning',
            ['changed' => $changed],
        );

        return back()->with('status', 'Credenciales Meraki actualizadas. Actualiza Meraki Dashboard si cambiaste el shared secret.');
    }
}
