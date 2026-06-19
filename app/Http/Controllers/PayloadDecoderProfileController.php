<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConnectorKind;
use App\Models\Connector;
use App\Models\PayloadDecoderProfile;
use App\Models\Product;
use App\Positioning\PayloadProfileDecoder;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use JsonException;

class PayloadDecoderProfileController extends Controller
{
    public function index(): View
    {
        return view('payload-profiles.index', [
            'profiles' => PayloadDecoderProfile::query()->with(['connector', 'products'])->orderBy('priority')->get(),
            'connectors' => Connector::query()->where('kind', ConnectorKind::Telemetry)->orderBy('name')->get(),
            'products' => Product::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        DB::transaction(function () use ($validated): void {
            $profile = PayloadDecoderProfile::query()->create(Arr::except($validated, 'product_ids'));
            $profile->products()->sync($validated['product_ids'] ?? []);
        });

        return back()->with('status', 'Perfil de payload guardado.');
    }

    public function update(Request $request, PayloadDecoderProfile $payloadProfile): RedirectResponse
    {
        $validated = $this->validated($request);
        DB::transaction(function () use ($payloadProfile, $validated): void {
            $payloadProfile->update(Arr::except($validated, 'product_ids'));
            $payloadProfile->products()->sync($validated['product_ids'] ?? []);
        });

        return back()->with('status', 'Perfil de payload actualizado.');
    }

    public function destroy(PayloadDecoderProfile $payloadProfile): RedirectResponse
    {
        $payloadProfile->delete();

        return back()->with('status', 'Perfil eliminado.');
    }

    public function preview(Request $request, PayloadDecoderProfile $payloadProfile, PayloadProfileDecoder $decoder): RedirectResponse
    {
        $request->validate(['sample_payload_json' => ['required', 'json']]);
        try {
            $payload = json_decode($request->string('sample_payload_json')->toString(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return back()->withErrors(['sample_payload_json' => 'El JSON no es válido.']);
        }

        $decodedPayload = data_get($payload, 'uplink_message.decoded_payload', []);
        $observations = $decoder->transform($decodedPayload, $payloadProfile);
        $payloadProfile->forceFill(['sample_payload' => $payload])->save();

        return back()->with('preview', [
            'profile_id' => $payloadProfile->id,
            'profile_name' => $payloadProfile->name,
            'decoded' => ['observations' => $observations],
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'connector_id' => ['required', TenantRule::exists('connectors')],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['distinct', TenantRule::exists('products')],
            'enabled' => ['sometimes', 'boolean'],
            'priority' => ['required', 'integer', 'min:1', 'max:1000'],
            'match_f_port' => ['nullable', 'integer', 'min:1', 'max:255'],
            'match_path' => ['nullable', 'string', 'max:255'],
            'match_value' => ['nullable', 'string', 'max:255', 'required_with:match_path'],
            'observations_path' => ['required', 'string', 'max:255'],
            'mac_path' => ['required', 'string', 'max:255'],
            'rssi_path' => ['required', 'string', 'max:255'],
            'receiver_path' => ['nullable', 'string', 'max:255'],
        ]) + ['enabled' => $request->boolean('enabled')];
    }
}
