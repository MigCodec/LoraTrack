@extends('layouts.app')

@section('title', 'Configurar '.$definition['name'])
@section('heading', 'Configurar '.$definition['name'])

@section('content')
    <div class="grid max-w-5xl gap-6 lg:grid-cols-[1fr_18rem]">
        <form method="POST" action="{{ route('connectors.store') }}" class="panel p-6">
            @csrf
            <input type="hidden" name="provider" value="{{ $definition['provider']->value }}">
            <div>
                <label class="field-label">Nombre del conector
                    <input class="field-input" name="name" value="{{ old('name', $definition['name']) }}" required maxlength="255">
                </label>
            </div>

            @if(count($definition['configuration']))
                <fieldset class="mt-8 border-t border-slate-100 pt-6">
                    <legend class="text-base font-semibold text-slate-900">Configuración</legend>
                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        @foreach($definition['configuration'] as $key => $field)
                            <label class="field-label {{ $field['type'] === 'checkbox' ? 'flex items-center gap-3' : '' }}">
                                @if($field['type'] === 'checkbox')
                                    <input type="hidden" name="configuration[{{ $key }}]" value="0">
                                    <input class="rounded border-slate-300" type="checkbox" name="configuration[{{ $key }}]" value="1" @checked(old("configuration.$key", $field['default'] ?? false))>
                                    {{ $field['label'] }}
                                @elseif($field['type'] === 'select')
                                    {{ $field['label'] }}
                                    <select class="field-input" name="configuration[{{ $key }}]" @required($field['required'])>
                                        @foreach($field['options'] as $value => $label)<option value="{{ $value }}" @selected(old("configuration.$key", $field['default'] ?? '') === $value)>{{ $label }}</option>@endforeach
                                    </select>
                                @else
                                    {{ $field['label'] }}
                                    <input class="field-input" type="{{ $field['type'] }}" name="configuration[{{ $key }}]" value="{{ old("configuration.$key", $field['default'] ?? '') }}" @required($field['required'])>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            @if(count($definition['credentials']))
                <fieldset class="mt-8 border-t border-slate-100 pt-6">
                    <legend class="text-base font-semibold text-slate-900">Credenciales</legend>
                    <p class="mt-1 text-xs text-slate-500">Se almacenarán cifradas y no volverán a mostrarse.</p>
                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        @foreach($definition['credentials'] as $key => $field)
                            <label class="field-label">{{ $field['label'] }}
                                <input class="field-input" id="credential-{{ $key }}" type="{{ $field['type'] }}" name="credentials[{{ $key }}]" value="{{ $field['type'] === 'password' ? '' : old("credentials.$key") }}" @required($field['required']) autocomplete="off">
                                @if($definition['provider']->value === 'tti_webhook' && $key === 'webhook_token')
                                    <span class="mt-2 flex flex-wrap items-center gap-2">
                                        <button class="btn-secondary" id="generate-webhook-token" type="button">Generar token seguro</button>
                                        <button class="text-xs font-semibold text-brand-primary" id="copy-webhook-token" type="button">Copiar</button>
                                        <span class="text-xs text-slate-500" id="webhook-token-status">Mínimo 24 caracteres.</span>
                                    </span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            <div class="mt-8 flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                <a class="btn-secondary" href="{{ route('connectors.index') }}">Cancelar</a>
                <button class="btn-primary" type="submit">Guardar conector</button>
            </div>
        </form>

        <aside class="panel h-fit p-5">
            <span class="connector-icon">{{ strtoupper(substr($definition['name'], 0, 2)) }}</span>
            <h2 class="mt-4 font-semibold text-slate-950">{{ $definition['name'] }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $definition['description'] }}</p>
            <p class="mt-5 rounded-lg bg-slate-50 p-3 text-xs leading-relaxed text-slate-500">El conector se guardará como borrador. Pruébalo antes de activarlo.</p>
        </aside>
    </div>

    @if($definition['provider']->value === 'tti_webhook')
        <script>
            (() => {
                const input = document.getElementById('credential-webhook_token');
                const status = document.getElementById('webhook-token-status');
                const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

                document.getElementById('generate-webhook-token').addEventListener('click', () => {
                    const bytes = crypto.getRandomValues(new Uint8Array(32));
                    input.value = Array.from(bytes, byte => alphabet[byte & 63]).join('');
                    input.type = 'text';
                    status.textContent = 'Token seguro generado: cópialo antes de guardar.';
                    input.focus();
                    input.select();
                });

                document.getElementById('copy-webhook-token').addEventListener('click', async () => {
                    if (! input.value) {
                        status.textContent = 'Primero genera un token.';
                        return;
                    }
                    await navigator.clipboard.writeText(input.value);
                    status.textContent = 'Token copiado.';
                });
            })();
        </script>
    @endif
@endsection
