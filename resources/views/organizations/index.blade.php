@extends('layouts.app')
@section('title', 'Identidad de la empresa')
@section('heading', 'Identidad de la empresa')
@section('content')
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <form class="panel space-y-6 p-6" method="POST" action="{{ route('organizations.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div>
                <h2 class="panel-title">{{ $current->name }}</h2>
                <p class="panel-subtitle">Estos cambios se aplican solamente a tu empresa o proyecto y se propagan a navegación, acciones, formularios, planos y comunicaciones.</p>
            </div>

            <label class="field-label">Nombre de la empresa o proyecto
                <input class="field-input" name="name" value="{{ old('name', $current->name) }}" required maxlength="255">
            </label>

            <div class="grid gap-4 md:grid-cols-3">
                @foreach(['primary_color' => 'Color principal', 'secondary_color' => 'Color secundario', 'accent_color' => 'Color de acento'] as $field => $label)
                    <label class="field-label">{{ $label }}
                        <span class="mt-2 flex items-center gap-2">
                            <input type="color" name="{{ $field }}" value="{{ old($field, $current->{$field}) }}" class="h-11 w-14 rounded border border-slate-300 bg-white p-1">
                            <input class="field-input font-mono" value="{{ old($field, $current->{$field}) }}" data-color-text="{{ $field }}" maxlength="7" aria-label="{{ $label }} hexadecimal">
                        </span>
                    </label>
                @endforeach
            </div>

            <div class="rounded-xl border border-slate-200 p-4">
                <label class="field-label">Logo de la empresa
                    <input class="field-input" type="file" name="logo" accept="image/png,image/jpeg,image/webp">
                </label>
                <p class="mt-2 text-xs text-slate-500">PNG, JPG o WEBP, máximo 4 MB. Se almacena de forma privada.</p>
                @if($current->logo_path)
                    <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_logo" value="1"> Eliminar logo actual</label>
                @endif
            </div>

            <details class="rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer font-semibold text-slate-950">Configuración avanzada</summary>
                <div class="mt-4 grid gap-4">
                    <label class="flex items-start gap-3">
                        <input type="hidden" name="storage_cleanup_enabled" value="0">
                        <input class="mt-1" type="checkbox" name="storage_cleanup_enabled" value="1" @checked(old('storage_cleanup_enabled', $current->storage_cleanup_enabled))>
                        <span>
                            <strong class="block text-sm text-slate-900">Liberar telemetría antigua por presión de almacenamiento</strong>
                            <span class="mt-1 block text-xs leading-relaxed text-slate-500">Función temporal. Cada hora comprueba el almacenamiento. Si la ocupación supera el 50%, elimina hasta 10.000 eventos y sus RSSI más antiguos de esta empresa por ejecución.</span>
                        </span>
                    </label>
                    <label class="field-label">Retención mínima de telemetría
                        <span class="mt-1 block text-xs font-normal leading-relaxed text-slate-500">Nunca se eliminarán eventos más recientes que este período. Las posiciones históricas se conservan.</span>
                        <span class="mt-2 flex max-w-sm items-center gap-2">
                            <input class="field-input" type="number" name="telemetry_retention_days" min="7" max="3650" value="{{ old('telemetry_retention_days', $current->telemetry_retention_days ?? 30) }}" required>
                            <span class="text-sm text-slate-500">días</span>
                        </span>
                    </label>
                    <dl class="grid gap-3 text-xs text-slate-600 sm:grid-cols-3">
                        <div><dt class="font-semibold text-slate-800">Última medición</dt><dd class="mt-1">{{ $current->storage_checked_at?->format('d-m-Y H:i') ?? 'Pendiente' }}</dd></div>
                        <div><dt class="font-semibold text-slate-800">Ocupación medida</dt><dd class="mt-1">{{ $current->last_storage_utilization_percent === null ? '—' : number_format($current->last_storage_utilization_percent, 2).'%' }}</dd></div>
                        <div><dt class="font-semibold text-slate-800">Eventos eliminados</dt><dd class="mt-1">{{ number_format($current->storage_cleanup_deleted_events ?? 0) }}</dd></div>
                    </dl>
                    <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">La medición requiere acceso al volumen donde vive la base de datos. Si la base es remota y ese volumen no está disponible para Laravel, el proceso falla de forma segura y no elimina información.</p>
                </div>
            </details>

            <button class="btn-primary">Guardar configuración de empresa</button>
        </form>

        <aside class="panel brand-preview h-fit overflow-hidden" data-brand-preview style="{{ App\Support\BrandPalette::cssVariables($current) }}">
            <div class="brand-preview-header p-5">
                @if($current->logo_path)
                    <img src="{{ route('organizations.logo') }}" alt="Logo de {{ $current->name }}" class="mb-4 max-h-20 max-w-full rounded bg-white p-2">
                @else
                    <img src="{{ asset('images/loratrack-default-logo.png') }}" alt="Logo predeterminado de LoraTrack" class="mb-4 h-20 w-20 rounded object-contain">
                @endif
                <strong class="block text-lg">{{ $current->name }}</strong>
                <span class="text-xs text-white/70">Vista previa</span>
            </div>
            <div class="space-y-3 p-5">
                <button class="btn-primary w-full" type="button">Acción principal</button>
                <button class="btn-secondary w-full" type="button">Acción secundaria</button>
                <div class="brand-preview-accent rounded p-3 text-xs font-semibold">Acento y selección</div>
                <div class="grid grid-cols-3 gap-2" aria-label="Muestras de la paleta">
                    <div class="brand-preview-swatch brand-preview-primary"></div>
                    <div class="brand-preview-swatch brand-preview-secondary"></div>
                    <div class="brand-preview-swatch brand-preview-accent-swatch"></div>
                </div>
            </div>
        </aside>
    </div>

    <script>
        const brandRgb = function (hex) {
            return [1, 3, 5].map(function (offset) { return parseInt(hex.slice(offset, offset + 2), 16); });
        };
        const brandLuminance = function (hex) {
            const channels = brandRgb(hex).map(function (channel) {
                const value = channel / 255;
                return value <= 0.04045 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
            });
            return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
        };
        const brandContrast = function (first, second) {
            const values = [brandLuminance(first), brandLuminance(second)].sort(function (a, b) { return b - a; });
            return (values[0] + 0.05) / (values[1] + 0.05);
        };
        const brandForeground = function (background) {
            return brandContrast(background, '#FFFFFF') >= brandContrast(background, '#0F172A') ? '#FFFFFF' : '#0F172A';
        };
        const brandMix = function (source, target, weight) {
            return '#' + brandRgb(source).map(function (channel, index) {
                return Math.round(channel * (1 - weight) + brandRgb(target)[index] * weight).toString(16).padStart(2, '0');
            }).join('');
        };
        const brandInk = function (color) {
            if (brandContrast(color, '#FFFFFF') >= 4.5) return color;
            for (let weight = 0.1; weight <= 1; weight += 0.1) {
                const candidate = brandMix(color, '#0F172A', weight);
                if (brandContrast(candidate, '#FFFFFF') >= 4.5) return candidate;
            }
            return '#0F172A';
        };
        document.querySelectorAll('[data-color-text]').forEach(function (text) {
            const picker = document.querySelector('[name="' + text.dataset.colorText + '"]');
            const preview = document.querySelector('[data-brand-preview]');
            const variable = {
                primary_color: '--color-brand-primary',
                secondary_color: '--color-brand-secondary',
                accent_color: '--color-brand-accent'
            }[text.dataset.colorText];
            const updatePreview = function (value) {
                if (! /^#[0-9a-f]{6}$/i.test(value)) return;
                preview.style.setProperty(variable, value);
                if (text.dataset.colorText === 'primary_color') {
                    preview.style.setProperty('--color-brand-on-primary', brandForeground(value));
                    preview.style.setProperty('--color-brand-primary-ink', brandInk(value));
                }
                if (text.dataset.colorText === 'secondary_color') {
                    preview.style.setProperty('--color-brand-on-secondary', brandForeground(value));
                }
                if (text.dataset.colorText === 'accent_color') {
                    preview.style.setProperty('--color-brand-energy', value);
                    preview.style.setProperty('--color-brand-on-accent', brandForeground(value));
                    preview.style.setProperty('--color-brand-accent-ink', brandInk(value));
                }
            };
            text.addEventListener('input', function () {
                if (/^#[0-9a-f]{6}$/i.test(text.value)) {
                    picker.value = text.value;
                    updatePreview(text.value);
                }
            });
            picker.addEventListener('input', function () {
                text.value = picker.value.toUpperCase();
                updatePreview(picker.value);
            });
        });
    </script>
@endsection
