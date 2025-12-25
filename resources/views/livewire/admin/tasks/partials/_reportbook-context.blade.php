@php
    /** @var \App\Models\AdminTask $task */
    /** @var \App\Models\ReportBook $reportBook */
    $reportBook  = $task->context;
    $course      = $reportBook->course ?? null;
    $user        = $reportBook->user ?? null;
    $signature   = $reportBook->participant_signature_file ?? null;
    $trainerSignature = $reportBook->trainer_signature_file ?? null;


    $entries = $reportBook->entries()->get();
@endphp

<h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
    Berichtsheft
</h3>

<div class="mt-2 space-y-4 text-sm text-slate-700">

    {{-- Meta-Infos zum Berichtsheft --}}
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p>
                    <span class="font-semibold text-slate-500">Kurs:</span>
                    {{ $course?->title ?? 'Unbekannter Kurs' }}
                </p>
                <p>
                    <span class="font-semibold text-slate-500">Klasse:</span>
                    {{ $course?->klassen_id ?? '—' }}
                </p>
                <p>
                    <span class="font-semibold text-slate-500">Teilnehmer:</span>
                    {{ $user?->name ?? 'Unbekannt' }}
                </p>
            </div>

            @if($reportBook->entries()->exists())
                <div class="text-right text-xs text-slate-500">
                    <p>
                        Einträge gesamt:
                        <span class="font-semibold text-slate-700">
                            {{ $reportBook->entries()->count() }}
                        </span>
                    </p>
                    @if(isset($reportBook->status) || isset($reportBook->status_label))
                        <p class="mt-1">
                            Status:
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700">
                                {{ $reportBook->status_label ?? ucfirst($reportBook->status) }}
                            </span>
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-max">
    {{-- Signatur des Teilnehmers --}}
    @if($signature)
        @php
            $sigUrl = $signature->getEphemeralPublicUrl(60) ?? null;
        @endphp

        @if($sigUrl)
            <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm w-max">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">
                    Unterschrift Teilnehmer/in
                </h4>

                <img
                    src="{{ $sigUrl }}"
                    alt="Unterschrift"
                    class="h-24 object-contain"
                >

                <a
                    href="{{ $sigUrl }}"
                    target="_blank"
                    class="mt-2 inline-flex items-center gap-1 text-xs text-blue-600 hover:underline"
                >
                    <i class="fal fa-download text-xs"></i>
                    herunterladen
                </a>
            </div>
        @endif
    @endif
    {{-- Signatur des Ausbilders --}}
    @if($trainerSignature)
        @php
            $trainerSigUrl = $trainerSignature->getEphemeralPublicUrl(60) ?? null;
        @endphp

        @if($trainerSigUrl)
            <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm w-max">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">
                    Unterschrift Ausbilder/in
                </h4>

                <img
                    src="{{ $trainerSigUrl }}"
                    alt="Unterschrift"
                    class="h-24 object-contain"
                >

                <a
                    href="{{ $trainerSigUrl }}"
                    target="_blank"
                    class="mt-2 inline-flex items-center gap-1 text-xs text-blue-600 hover:underline"
                >
                    <i class="fal fa-download text-xs"></i>
                    herunterladen
                </a>
            </div>
        @endif
    @endif
</div>
    {{-- Einträge aus dem Berichtsheft --}}
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Berichtsheft-Einträge
            </h4>
            @if($entries->count() > 0)
                <span class="text-[11px] text-slate-500">
                    {{ $entries->count() }} Eintrag{{ $entries->count() === 1 ? '' : 'e' }}
                </span>
            @endif
        </div>

        @if($entries->isEmpty())
            <p class="text-xs text-slate-500">
                Keine Einträge im Berichtsheft vorhanden.
            </p>
        @else
            <div class="mt-1 space-y-3 max-h-80 overflow-y-auto pr-1">
                @foreach($entries as $entry)
                    @php
                        $entryDate = optional(
                            $entry->date
                                ?? $entry->entry_date
                                ?? $entry->created_at
                        )->format('d.m.Y');

                        $statusLabel = $entry->status_label
                            ?? ucfirst($entry->status ?? '');
                    @endphp

                    <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                        <div class="flex items-center justify-between gap-2 text-[11px] text-slate-500">
                            <span>{{ $entryDate ?? '—' }}</span>
                            @if($statusLabel)
                                <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-[10px] font-medium text-slate-600">
                                    {{ $statusLabel }}
                                </span>
                            @endif
                        </div>

                        @if(!empty($entry->title))
                            <div class="mt-1 font-semibold text-xs text-slate-700">
                                {!! $entry->title !!}
                            </div>
                        @endif

                        <div class="mt-1 text-sm text-slate-700 leading-relaxed">
                            @if(!empty($entry->text))
                                {!! $entry->text !!}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Freigabe-Button: nur für zugewiesenen Mitarbeiter und offene Aufgaben --}}
    @php
        $currentUserId = auth()->id();
    @endphp


        <div class="flex justify-end">
            <x-secondary-button
                wire:click="approveReportBook"
                class="mt-2 inline-flex items-center gap-2 bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100"
            >
                <i class="fal fa-check-double text-xs"></i>
                Berichtsheft freigeben
            </x-secondary-button>
        </div>

        <livewire:tools.signatures.signature-form lazy />
</div>
