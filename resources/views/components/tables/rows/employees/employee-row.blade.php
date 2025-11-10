@php
    // Kurzhelfer pro Spaltenindex (kommt vom x-tables.table)
    $hc = fn($i) => $hideClass($columnsMeta[$i]['hideOn'] ?? 'none');

    /** @var \App\Models\User $item */
    $name   = $item->name ?? '—';
    $email  = $item->email ?? '—';
    $team   = $item->currentTeam?->name ?? '—';
    $created= optional($item->created_at)->locale('de')->isoFormat('ll');

    // optional: ausgewählt-Status, falls du ihn mitlieferst
    $isSelected = in_array($item->id, $selectedItems ?? [], true);
@endphp

{{-- 0: Name (mit Auswahl-Kreis wie bei Courses) --}}
<div class="px-2 py-2 pr-4 {{ $hc(0) }} cursor-pointer" wire:click="$dispatch('toggleEmployeeSelection', [{{ $item->id }}])">
    <div class="grid grid-cols-[auto_1fr] gap-2 items-center">
        <div class="flex items-center">
            <div
                class="w-4 h-4 rounded-full border cursor-pointer transition
                {{ $isSelected ? 'ring-4 ring-green-300 bg-green-100 border-green-600' : 'border-gray-400' }}">
            </div>
        </div>

        {{-- WICHTIG: min-w-0 damit truncate greift --}}
        <div class="flex flex-col min-w-0">
            <div class="px-1 font-semibold truncate">
                {{ $name }}
            </div>
        </div>
    </div>
</div>

{{-- 1: E-Mail --}}
<div class="px-2 py-2 text-gray-700 truncate {{ $hc(1) }}">
    <a href="mailto:{{ $email }}" class="hover:underline">{{ $email }}</a>
</div>

{{-- 2: Team (Badge) --}}
<div class="flex items-center px-2 py-2 text-xs {{ $hc(2) }}">
    <span class="inline-flex items-center px-2 py-0.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-700 shadow-sm">
        {{ $team }}
    </span>
</div>

{{-- 3: Erstellt am --}}
<div class="px-2 py-2 text-gray-600 {{ $hc(3) }} ">
    <div class="pr-8">
        {{ $created ?? '—' }}
    </div>
</div>


