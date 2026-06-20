<div class="mt-4 space-y-3">
    @forelse($installations as $installation)
        <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 p-3">
            <div><p class="text-sm font-semibold">{{ $installation->device->name }}</p><p class="text-xs text-slate-400">({{ number_format((float) $installation->x, 2) }}, {{ number_format((float) $installation->y, 2) }}) m · {{ $installation->reference_rssi }} dBm</p></div>
            @if(auth()->user()->hasPermission('plans.manage'))<form method="POST" action="{{ route('installations.destroy', $installation) }}">@csrf @method('DELETE')<button class="text-xs text-red-600">Quitar</button></form>@endif
        </div>
    @empty
        <p class="text-sm text-slate-400">Se requieren al menos tres anclas no colineales.</p>
    @endforelse
</div>
