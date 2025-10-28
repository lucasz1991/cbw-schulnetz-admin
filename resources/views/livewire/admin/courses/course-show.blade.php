<div class="px-4 py-4 space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ $course->title ?? 'Kurs' }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                @if($course->klassen_id)
                    <span class="px-2 py-0.5 rounded border border-slate-200 bg-slate-50 text-slate-700">Klasse: {{ $course->klassen_id }}</span>
                @endif
                @if($course->termin_id)
                    <span class="px-2 py-0.5 rounded border border-slate-200 bg-slate-50 text-slate-700">Termin: {{ $course->termin_id }}</span>
                @endif
                @if($course->room)
                    <span class="px-2 py-0.5 rounded border border-amber-200 bg-amber-50 text-amber-700">Raum {{ $course->room }}</span>
                @endif 

                {{-- Zeitraum --}}
                <span class="px-2 py-0.5 rounded border border-green-200 bg-green-50 text-green-700">
                    {{ optional($course->planned_start_date)->locale('de')->isoFormat('ll') ?? '—' }}
                    –
                    {{ optional($course->planned_end_date)->locale('de')->isoFormat('ll') ?? '—' }}
                </span>
                @php
                    $status = $this->status;
                    $badge = match($status) {
                        'planned'  => ['bg' => 'bg-sky-50',     'text' => 'text-sky-700',     'label' => 'Geplant'],
                        'active'   => ['bg' => 'bg-green-50',   'text' => 'text-green-700',   'label' => 'Aktiv'],
                        'finished' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'label' => 'Abgeschlossen'],
                        default    => ['bg' => 'bg-gray-50',    'text' => 'text-gray-600',    'label' => '—'],
                    };
                @endphp
                <span class="px-2 py-0.5 rounded border border-gray-200 {{ $badge['bg'] }} {{ $badge['text'] }}">{{ $badge['label'] }}</span>
                @if($course->is_active)
                    <span class="px-2 py-0.5 rounded border border-lime-200 bg-lime-50 text-lime-700">aktiv</span>
                @else
                    <span class="px-2 py-0.5 rounded border border-gray-200 bg-gray-50 text-gray-600">inaktiv</span>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-link-button href="{{ url()->previous() }}" class="btn-xs">← Zurück</x-link-button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 rounded-2xl border border-neutral-200 bg-white">
            <div class="text-xs text-neutral-500 mb-1">Tutor</div>
            <div class="font-medium">
                @if($course->tutor)
                    <x-user.public-info :person="$course->tutor" />
                @else
                    <span class="text-neutral-400">—</span>
                @endif
            </div>
        </div>

        <div class="p-4 rounded-2xl border border-neutral-200 bg-white">
            <div class="text-xs text-neutral-500 mb-1">Teilnehmer</div>
            <div class="font-medium">{{ (int)($course->participants_count ?? 0) }}</div>
        </div>

        <div class="p-4 rounded-2xl border border-neutral-200 bg-white">
            <div class="text-xs text-neutral-500 mb-1">Termine</div>
            <div class="font-medium">{{ (int)($course->dates_count ?? 0) }}</div>
        </div>
    </div>

    @if($course->description)
        <div class="p-4 rounded-2xl border border-neutral-200 bg-white">
            <div class="text-xs text-neutral-500 mb-2">Beschreibung</div>
            <div class="prose max-w-none">{{ $course->description }}</div>
        </div>
    @endif

    <div class="p-4 rounded-2xl border border-neutral-200 bg-white">
        <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-semibold">Unterrichtstage</div>
        </div>
        @if($course->days->isNotEmpty())
            <ul class="divide-y divide-neutral-100">
                @foreach($course->days as $d)
                    <li class="py-2 text-sm flex justify-between">
                        <span>{{ optional($d->date)->locale('de')->isoFormat('dd, ll') }}</span>
                        <span class="text-neutral-500">
                            @if(!empty($d->start_time) || !empty($d->end_time))
                                {{ $d->start_time ?? '—' }}–{{ $d->end_time ?? '—' }}
                            @endif
                            @if(!empty($d->room))
                                · Raum {{ $d->room }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="text-sm text-neutral-400">Keine Termine vorhanden.</div>
        @endif
    </div>

    <div class="p-4 rounded-2xl border border-neutral-200 bg-white">
        <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-semibold">Teilnehmer</div>
        </div>
        @if($course->participants->isNotEmpty())
            <ul class="grid md:grid-cols-2 gap-2 text-sm">
                @foreach($course->participants as $p)
                    <li class="px-3 py-2 ">
                        <x-user.public-info :person="$p" />
                    </li>
                @endforeach
            </ul>
        @else
            <div class="text-sm text-neutral-400">Keine Teilnehmer vorhanden.</div>
        @endif
    </div>
</div>
