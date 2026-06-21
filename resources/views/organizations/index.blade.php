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
                <p class="panel-subtitle">Estos cambios se aplican solamente a tu empresa o proyecto.</p>
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

            <button class="btn-primary">Guardar identidad visual</button>
        </form>

        <aside class="panel h-fit overflow-hidden">
            <div class="p-5" style="background: {{ $current->secondary_color }}; color: white">
                @if($current->logo_path)
                    <img src="{{ route('organizations.logo') }}" alt="Logo de {{ $current->name }}" class="mb-4 max-h-20 max-w-full rounded bg-white p-2">
                @else
                    <img src="{{ asset('images/loratrack-default-logo.png') }}" alt="Logo predeterminado de LoraTrack" class="mb-4 h-20 w-20 rounded object-contain">
                @endif
                <strong class="block text-lg">{{ $current->name }}</strong>
                <span class="text-xs text-white/70">Vista previa</span>
            </div>
            <div class="space-y-3 p-5">
                <div class="h-3 rounded" style="background: {{ $current->primary_color }}"></div>
                <div class="h-3 rounded" style="background: {{ $current->secondary_color }}"></div>
                <div class="h-3 rounded" style="background: {{ $current->accent_color }}"></div>
            </div>
        </aside>
    </div>

    <script>
        document.querySelectorAll('[data-color-text]').forEach(function (text) {
            const picker = document.querySelector('[name="' + text.dataset.colorText + '"]');
            text.addEventListener('input', function () { if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value; });
            picker.addEventListener('input', function () { text.value = picker.value.toUpperCase(); });
        });
    </script>
@endsection
