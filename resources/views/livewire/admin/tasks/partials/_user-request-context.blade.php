@php
    /** @var \App\Models\AdminTask $task */
    /** @var \App\Models\UserRequest $req */
    $req    = $task->context;
    $user   = $req->user ?? null;
    $person = $user?->person ?? null;
    $course = $req->course ?? null;

    $fullName = $person
        ? trim(($person->nachname ?? '') . ', ' . ($person->vorname ?? ''))
        : ($user?->name ?? 'Unbekannt');

    $klasse = $req->class_code
        ?? $course?->courseClassName
        ?? $course?->klassen_id
        ?? '—';

    $createdAt = optional($req->created_at)->format('d.m.Y H:i');

    $type      = $req->type ?? 'unknown';
    $typeLabel = $req->type_label
        ?? match($type) {
            'absence'       => 'Fehlzeitmeldung',
            'makeup'        => 'Antrag Nachprüfung',
            'external_exam' => 'Anmeldung externe Prüfung',
            default         => 'Antrag',
        };
@endphp

<h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
    Antrag
</h3>

<div class="mt-1 text-sm text-slate-700 space-y-1">
    <p>
        <strong>Typ:</strong>
        {{ $typeLabel }}
    </p>
    <p>
        <strong>Teilnehmer:</strong>
        <div>
            <x-user.public-info :person="$person" />
        </div>
    </p>
    <p>
        <strong>Klasse:</strong>
        {{ $klasse }}
    </p>
    <p class="text-xs text-slate-500">
        Eingereicht am {{ $createdAt ?? '—' }}
    </p>
</div>

<div class="mt-4 space-y-4 text-sm text-slate-700">

{{-- 1) FEHLZEITMELDUNG --}}
@if($type === 'absence')
    @php
        $date = optional($req->date_from ?? $req->created_at)->format('d.m.Y');
    @endphp

    {{-- SECTION: Fehlzeiten-Infos --}}
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm space-y-4">

        {{-- Titel --}}
        <div class="flex items-center gap-2">
            <i class="fal fa-calendar-times text-slate-500"></i>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600">
                Fehlzeit-Informationen
            </h4>
        </div>

        {{-- Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-slate-700">

            <div class="space-y-1">
                <p><span class="font-semibold text-slate-500">Datum:</span> {{ $date ?? '—' }}</p>
                <p><span class="font-semibold text-slate-500">Ganztags gefehlt:</span> {{ $req->full_day ? 'Ja' : 'Nein' }}</p>
            </div>

            <div class="space-y-1">
                <p>
                    <span class="font-semibold text-slate-500">Später gekommen:</span>
                    {{ $req->time_arrived_late ? $req->time_arrived_late.' Uhr' : '—' }}
                </p>
                <p>
                    <span class="font-semibold text-slate-500">Früher gegangen:</span>
                    {{ $req->time_left_early ? $req->time_left_early.' Uhr' : '—' }}
                </p>
            </div>
        </div>
    </div>


    {{-- SECTION: Grund der Abwesenheit --}}
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 shadow-sm mt-4">
        <div class="flex items-center gap-2 mb-2">
            <i class="fal fa-info-circle text-slate-500"></i>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600">
                Grund der Abwesenheit
            </h4>
        </div>

        <div class="text-sm text-slate-700 leading-relaxed">
            @if($req->reason !== 'abw_unwichtig')
                <strong class="text-slate-600">Wichtig:</strong>
            @endif
            {{ $req->reason_item ?? 'Fehlzeit ohne wichtigen Grund' }}
        </div>
    </div>


    {{-- SECTION: Sonstige Begründung --}}
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 shadow-sm mt-4">
        <div class="flex items-center gap-2 mb-2">
            <i class="fal fa-comment-lines text-slate-500"></i>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600">
                Weitere Angaben
            </h4>
        </div>

        <div class="text-sm text-slate-700 whitespace-pre-line leading-relaxed">
            {{ $req->message ?: '—' }}
        </div>
    </div>



    {{-- 2) NACHPRÜFUNG --}}
    @elseif($type === 'makeup')
        @php
            $originalExam = $req->original_exam_date
                ? \Carbon\Carbon::parse($req->original_exam_date)->format('d.m.Y')
                : '—';

            $requestedExam = $req->exam_date
                ? \Carbon\Carbon::parse($req->exam_date)->format('d.m.Y')
                : '—';

            $examModality = match($req->exam_modality ?? null) {
                'retake'      => 'Nach-/Wiederholungsprüfung (20,00 €)',
                'improvement' => 'Nachprüfung zwecks Ergebnisverbesserung (40,00 €)',
                default       => 'Nicht angegeben',
            };
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Prüfung
                </h4>
                <div class="mt-1 space-y-1">
                    <p><strong>Modul / Baustein:</strong> {{ $req->module_code ?? '—' }}</p>
                    <p><strong>Instruktor / Dozent:</strong> {{ $req->instructor_name ?? '—' }}</p>
                    <p><strong>ursprünglicher Termin:</strong> {{ $originalExam }}</p>
                    <p><strong>gewünschter Nachprüfungstermin:</strong> {{ $requestedExam }}</p>
                </div>
            </div>

            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Art der Nachprüfung
                </h4>
                <div class="mt-1 rounded border border-slate-200 bg-slate-50 px-3 py-2">
                    {{ $examModality }}
                </div>

                <h4 class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Begründung (Auswahl)
                </h4>
                <div class="mt-1 space-y-1 text-xs">
                    <p>
                        <span class="inline-block w-3 text-center">
                            {{ $req->reason_item === 'noten_unter_51' ? '•' : '○' }}
                        </span>
                        ursprüngliche Prüfung unter 51 Punkte
                    </p>
                    <p>
                        <span class="inline-block w-3 text-center">
                            {{ $req->reason_item === 'krank_mit_attest' ? '•' : '○' }}
                        </span>
                        Krankheit am Prüfungstag, mit Attest
                    </p>
                    <p>
                        <span class="inline-block w-3 text-center">
                            {{ $req->reason_item === 'krank_ohne_attest' ? '•' : '○' }}
                        </span>
                        Krankheit am Prüfungstag, ohne Attest
                    </p>
                </div>
            </div>
        </div>

        @if(!empty($req->message))
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Zusätzliche Begründung
                </h4>
                <div class="mt-1 rounded border border-slate-200 bg-slate-50 px-3 py-2 whitespace-pre-line">
                    {{ $req->message }}
                </div>
            </div>
        @endif

    {{-- 3) EXTERNE PRÜFUNG --}}
    @elseif($type === 'external_exam')
        @php
            $externalExamDate = $req->external_exam_date
                ? \Carbon\Carbon::parse($req->external_exam_date)->format('d.m.Y')
                : '—';

            $courseLabel = $course?->courseShortName
                ?? $course?->title
                ?? '—';
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Prüfung
                </h4>
                <div class="mt-1 space-y-1">
                    <p><strong>Baustein / Kurs:</strong> {{ $courseLabel }}</p>
                    <p><strong>Klasse:</strong> {{ $klasse }}</p>
                    <p><strong>Prüfungstermin extern:</strong> {{ $externalExamDate }}</p>
                </div>
            </div>

            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Prüfungsinstitution
                </h4>
                <div class="mt-1 rounded border border-slate-200 bg-slate-50 px-3 py-2">
                    {{ $req->external_institution ?? '—' }}
                </div>

                <h4 class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Externe Prüfungsbezeichnung
                </h4>
                <div class="mt-1 rounded border border-slate-200 bg-slate-50 px-3 py-2">
                    {{ $req->external_exam_name ?? '—' }}
                </div>
            </div>
        </div>

        @if(!empty($req->reason))
            <div class="mt-3">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Zusätzliche Hinweise / Begründung
                </h4>
                <div class="mt-1 rounded border border-slate-200 bg-slate-50 px-3 py-2 whitespace-pre-line">
                    {{ $req->reason }}
                </div>
            </div>
        @endif

    @else
        <p class="text-sm text-slate-500">
            Für diesen Antrags-Typ ist noch keine Detailansicht definiert.
        </p>
    @endif

    @if($req?->files?->count())
        <div class="mt-4 space-y-2 pb-4">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Anhänge
            </h4>

            @foreach($req->files as $attachment)
                <x-ui.filepool.file-card-slim :file="$attachment" />
            @endforeach
        </div>
    @endif
</div>
