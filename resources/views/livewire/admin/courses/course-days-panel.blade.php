<div x-data="{ openId: null }" class="space-y-5">
    @if($days->isEmpty())
        <div class="text-sm text-neutral-400">
            Keine Termine vorhanden.
        </div>
    @else
        <div x-data="{ signCardOpen: false }" class="relative overflow-hidden rounded-2xl border shadow-sm {{ $this->dokuSigned ? 'border-emerald-300 bg-emerald-50/40' : 'border-amber-300 bg-amber-50/40' }}">
            <button
                type="button"
                class="relative w-full p-2 text-left {{ $this->dokuSigned ? 'cursor-pointer' : 'cursor-default' }}"
                @click="{{ $this->dokuSigned ? 'signCardOpen = !signCardOpen' : '' }}"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full border {{ $this->dokuSigned ? 'border-emerald-200 bg-emerald-100 text-emerald-700' : 'border-amber-200 bg-amber-100 text-amber-700' }}">
                            <i class="fal fa-signature text-sm"></i>
                        </div>
                        <div>
                            <div class="text-[11px] uppercase tracking-wide text-slate-800">Baustein-Doku Teilnehmer-Bestätigung</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold border {{ $this->dokuSigned ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-amber-100 text-amber-800 border-amber-300' }}">
                            <i class="fal {{ $this->dokuSigned ? 'fa-check-circle' : 'fa-clock' }} text-[12px]"></i>
                            <span>{{ $this->dokuSigned ? 'Unterschrieben' : 'Ausstehend' }}</span>
                        </span>
                        @if($this->dokuSigned)
                            <i class="fal fa-chevron-down text-slate-700 text-base transition-transform" :class="signCardOpen ? 'rotate-180' : ''"></i>
                        @endif
                    </div>
                </div>
            </button>
            <div x-show="signCardOpen" x-collapse x-cloak class="relative border-t {{ $this->dokuSigned ? 'border-emerald-200/70 bg-white/90' : 'border-amber-200/70 bg-white/90' }} p-4 md:p-5 text-xs">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <div class="text-[11px] uppercase tracking-wide text-slate-400">Teilnehmer / Unterzeichner</div>
                        <div class="mt-1.5 text-slate-800">
                            @if($this->classRepresentativePerson)
                                <x-user.public-info :person="$this->classRepresentativePerson" />
                            @else
                                <span class="text-slate-400">Nicht zugewiesen</span>
                            @endif
                        </div>
                        <div class="mt-2 inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-[11px] text-slate-600">
                            <i class="fal fa-calendar-alt text-[11px]"></i>
                            @if($this->signedAt)
                                {{ \Carbon\Carbon::parse($this->signedAt)->format('d.m.Y H:i') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <div class="text-[11px] uppercase tracking-wide text-slate-400">Signaturdatei</div>
                        @if($this->dokuSignatureFile)
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-xs font-medium text-slate-700">{{ $this->dokuSignatureFile->name ?? 'Signaturdatei' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $this->dokuSignatureFile->size_formatted ?? '' }}</div>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs text-sky-700 hover:bg-sky-100"
                                    @click="window.dispatchEvent(new CustomEvent('filepool-preview', { detail: { id: {{ $this->dokuSignatureFile->id }} } }))"
                                >
                                    <i class="fal fa-eye text-[12px]"></i>
                                    Vorschau
                                </button>
                            </div>
                        @else
                            <div class="mt-1 text-slate-400">Keine Signaturdatei vorhanden.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-between text-xs text-neutral-500 mb-2">
            <span class="font-semibold text-neutral-700">Unterrichtstage</span>
            <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 text-sky-700 border border-sky-100 px-2 py-0.5">
                <i class="fal fa-layer-group text-[11px]"></i>
                <span>{{ $days->count() }} Einheiten</span>
            </span>
        </div>
        <div class="space-y-3">
            @foreach($days as $day)
                <div
                    wire:key="course-day-{{ $day['id'] }}"
                    class="relative rounded-2xl border border-neutral-100 bg-white shadow-sm/5 overflow-hidden transition duration-150 hover:shadow-md"
                    :class="{ 'border-sky-200 bg-sky-50/40': openId === {{ $day['id'] }} }"
                >
                    <div class="absolute left-0 top-0 h-full w-1 bg-sky-100" aria-hidden="true"></div>

                    <button
                        type="button"
                        class="w-full px-4 py-3 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between text-left"
                        @click="openId = (openId === {{ $day['id'] }}) ? null : {{ $day['id'] }}"
                    >
                        <div>
                            <div class="flex items-center gap-3">
                                <div class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-sky-50 border border-sky-100">
                                    <i class="fad fa-calendar-day text-[14px] text-sky-600"></i>
                                </div>
                                <div class="leading-tight">
                                    <div class="text-sm font-semibold text-neutral-800">
                                        {{ $day['date']->locale('de')->isoFormat('dddd, ll') }}
                                    </div>
                                    <div class="text-xs text-neutral-500">
                                        @if($day['start_time'] || $day['end_time'])
                                            {{ $day['start_time'] ?? '' }}-{{ $day['end_time'] ?? '' }}
                                        @else
                                            Zeit nicht hinterlegt
                                        @endif
                                        @if($day['room'])
                                           - Raum {{ $day['room'] }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 sm:mt-0 flex flex-wrap items-center gap-2 text-[11px]">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] {{ $day['note_status_classes'] }}">
                                <i class="fal fa-flag text-[11px]"></i>
                                <span>Doku: {{ $day['note_status_label'] }}</span>
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5
                                {{ $day['has_attendance'] ? 'bg-sky-50 text-sky-700 border-sky-200' : 'bg-neutral-50 text-neutral-500 border-neutral-200' }}">
                                <i class="fal fa-user-check text-[11px]"></i>
                                <span>{{ $day['has_attendance'] ? 'Anwesenheit erfasst' : 'Keine Anwesenheit' }}</span>
                            </span>
                        </div>
                    </button>
                    <div x-show="openId === {{ $day['id'] }}" x-collapse x-cloak>
                        <div class="px-4 pb-4 border-t border-dashed border-neutral-200 bg-neutral-50/50">
                            <div class="pt-3 space-y-3">
                                <div class="rounded-xl border border-neutral-100 bg-white p-3 shadow-sm/5">
                                    @php $a = $day['attendance']; @endphp
                                    <div class="mb-2 text-[11px] uppercase tracking-wide text-neutral-400">Anwesenheit</div>

                                    @if(auth()->user()?->isAdmin())
                                        <button
                                            type="button"
                                            wire:click="openAttendanceEditor({{ $day['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openAttendanceEditor({{ $day['id'] }})"
                                            class="group flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2.5 text-left transition hover:border-sky-300 hover:bg-sky-50/70 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60"
                                            aria-label="Anwesenheitsliste für {{ $day['date']->format('d.m.Y') }} öffnen und bearbeiten"
                                        >
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-sky-200 bg-white text-sky-700 shadow-sm transition group-hover:border-sky-300">
                                                <i class="fad fa-clipboard-user" aria-hidden="true"></i>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-xs font-semibold text-slate-800">Anwesenheitsliste</span>
                                                <span class="mt-0.5 block text-[11px] text-slate-500">
                                                    {{ $day['participants_count'] }} Teilnehmer · {{ $a['total'] }} Einträge
                                                </span>
                                            </span>
                                            <span class="hidden items-center gap-1.5 sm:flex" aria-label="Kurzübersicht">
                                                <span class="inline-flex min-w-8 items-center justify-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-1 text-[10px] font-semibold text-emerald-700" title="Anwesend">
                                                    <i class="fad fa-user-check" aria-hidden="true"></i>{{ $a['present'] }}
                                                </span>
                                                <span class="inline-flex min-w-8 items-center justify-center gap-1 rounded-md border border-rose-200 bg-rose-50 px-1.5 py-1 text-[10px] font-semibold text-rose-700" title="Fehlend">
                                                    <i class="fad fa-user-times" aria-hidden="true"></i>{{ $a['absent'] }}
                                                </span>
                                                <span class="inline-flex min-w-8 items-center justify-center gap-1 rounded-md border border-blue-200 bg-blue-50 px-1.5 py-1 text-[10px] font-semibold text-blue-700" title="Entschuldigt">
                                                    <i class="fad fa-file-medical" aria-hidden="true"></i>{{ $a['excused'] }}
                                                </span>
                                            </span>
                                            <i class="fad fa-chevron-right text-xs text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-sky-600" aria-hidden="true"></i>
                                        </button>
                                    @else
                                        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2.5 text-xs text-slate-600">
                                            <i class="fad fa-clipboard-user text-sky-700" aria-hidden="true"></i>
                                            <span>{{ $day['participants_count'] }} Teilnehmer · {{ $a['total'] }} Anwesenheitseinträge</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="rounded-xl border border-neutral-100 bg-white p-3 shadow-inner/5">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="text-[11px] uppercase tracking-wide text-neutral-400">Dokumentation</div>
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] {{ $day['note_status_classes'] }}">
                                            <i class="fal fa-flag text-[11px]"></i>
                                            <span>{{ $day['note_status_label'] }}</span>
                                        </span>
                                    </div>
                                    @if(!empty($day['notes_html']))
                                        <div class="prose prose-sm max-w-none">
                                            {!! $day['notes_html'] !!}
                                        </div>
                                    @else
                                        <div class="text-xs text-neutral-400">
                                            Keine Unterrichtsdokumentation hinterlegt.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <livewire:tools.file-pools.file-preview-modal />
    @endif
</div>
