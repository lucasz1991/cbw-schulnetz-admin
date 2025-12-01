<x-modal.modal wire:model="showDetailModal">

    {{-- TITLE --}}
    <x-slot name="title">
        @if($task)
            <div class="flex flex-col gap-1">
                <div class="flex items-center justify-between gap-3">

                    {{-- Linke Seite --}}
                    <div class="flex items-center gap-2">
                        <i class="fal fa-tasks text-slate-500 text-lg"></i>
                        <span class="font-semibold">Aufgabe #{{ $task->id }}</span>
                    </div>

                    {{-- Rechte Seite: Zuweisung --}}
                    <div class="flex items-center gap-2 text-xs">
                        @if($task->assignedAdmin)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                                <i class="fal fa-user-check text-[11px]"></i>
                                <span>{{ $task->assignedAdmin->name }}</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">
                                <i class="fal fa-user-clock text-[11px]"></i>
                                <span>Nicht zugewiesen</span>
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @else
            Aufgabe
        @endif
    </x-slot>

    {{-- CONTENT --}}
    <x-slot name="content">

        @if(!$task)
            <div class="py-6 text-center text-slate-500">
                Keine Aufgabe geladen.
            </div>
        @else

            {{-- VIEW: Aufgabenübersicht --}}
            @if($viewMode === 'task')
                <div class="space-y-6">
                    {{-- Zeiten & Verursacher --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        {{-- Verursacher --}}
                        <div class="space-y-1">
                            <h3 class="text-xs  text-slate-500">Verursacher</h3>
                            <x-user.public-info :person="$task->creator->person" />
                            <p class="mt-1 text-sm text-slate-700 w-max">
                            </p>
                        </div>
                        {{-- Zeiten --}}
                        <div class="space-y-1 ">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Zeiten</h3>
                            <p class="text-sm">
                                <strong>Erstellt:</strong>
                                {{ $task->created_at->format('d.m.Y H:i') }}
                            </p>

                            @if($task->due_at)
                                <p class="text-sm">
                                    <strong>Fällig:</strong>
                                    {{ $task->due_at->format('d.m.Y H:i') }}
                                </p>

                                @if($task->is_overdue)
                                    <p class="text-xs text-red-600 font-semibold">Überfällig</p>
                                @endif
                            @endif
                        </div>



                    </div>
                    {{-- Kontextkurzinfo --}}
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kontext</h3>
                        <p class="mt-1 text-sm text-slate-700">
                            {{ $task->context_text ?? 'Kein Kontext' }}
                        </p>
                    </div>
                    {{-- Beschreibung --}}
                    <div class="">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Beschreibung
                        </h3>
                        <p class="mt-1 text-slate-700 text-sm">
                            {{ $task->description ?: 'Keine Beschreibung angegeben.' }}
                        </p>
                    </div>




                </div>
            @endif

            {{-- VIEW: Kontext --}}
            @if($viewMode === 'context' && $task->context)

                <div class="space-y-4">

                    {{-- BERICHTSHEFT --}}
                    @if($task->task_type === 'reportbook_review')

                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Berichtsheft
                        </h3>

                        <div class="text-sm text-slate-700 space-y-1">
                            <p>
                                <strong>Kurs:</strong>
                                {{ $task->context->course->title ?? 'Unbekannter Kurs' }}
                            </p>
                            <p>
                                <strong>Teilnehmer:</strong>
                                {{ $task->context->participant->user->name ?? 'Unbekannt' }}
                            </p>
                        </div>

{{-- USER REQUEST --}}
@elseif($task->task_type === 'user_request_review' && $task->context)
    @php
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

        // kleine Helper für Lesbarkeit
        $type       = $req->type ?? 'unknown';
        $typeLabel  = $req->type_label
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
            {{ $fullName }}
        </p>
        <p>
            <strong>Klasse:</strong>
            {{ $klasse }}
        </p>
        <p class="text-xs text-slate-500">
            Eingereicht am {{ $createdAt ?? '—' }}
        </p>
    </div>

    {{-- Je nach Antrags-Typ unterschiedliche Details --}}
    <div class="mt-4 space-y-4 text-sm text-slate-700">

        {{-- 1) FEHLZEITMELDUNG --}}
        @if($type === 'absence')
            @php
                $date = optional($req->date_from ?? $req->created_at)->format('d.m.Y');
            @endphp

            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Fehlzeit
                </h4>

                <div class="mt-1 space-y-1">
                    <p>
                        <strong>Datum:</strong>
                        {{ $date ?? '—' }}
                    </p>
                    <p>
                        <strong>Später gekommen:</strong>
                        {{ $req->time_arrived_late ? $req->time_arrived_late.' Uhr' : '---' }}
                    </p>
                    <p>
                        <strong>Früher gegangen:</strong>
                        {{ $req->time_left_early ? $req->time_left_early.' Uhr' : '---' }}
                    </p>
                    <p>
                        <strong>Ganztags gefehlt:</strong>
                        {{ $req->full_day ? 'Ja' : 'Nein' }}
                    </p>
                </div>
            </div>

            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Grund der Abwesenheit
                </h4>
                <div class="mt-1 rounded border border-slate-200 bg-slate-50 px-3 py-2">
                    {{ $req->reason === 'abw_unwichtig' ? '' : 'Wichtig = ' }}
                    {{ $req->reason_item ?? 'Fehlzeit ohne wichtigen Grund' }}
                </div>
            </div>

            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Sonstige Begründung
                </h4>
                <div class="mt-1 rounded border border-slate-200 bg-slate-50 px-3 py-2 whitespace-pre-line">
                    {{ $req->message ?? '—' }}
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

        {{-- Fallback --}}
        @else
            <p class="text-sm text-slate-500">
                Für diesen Antrags-Typ ist noch keine Detailansicht definiert.
            </p>
        @endif

    </div>
@endif

                </div>

            @endif

        @endif
    </x-slot>

    {{-- FOOTER --}}
    <x-slot name="footer">
        @if($task)

            @php $currentUserId = auth()->id(); @endphp

            <div class="flex justify-between w-full items-center">

                <div class="flex gap-2">

                    {{-- Kontext-Button: nur wenn übernommen UND Kontext existiert --}}
                    @if(
                        $task->context &&
                        (int) $task->assigned_to === (int) $currentUserId
                    )
                        <x-secondary-button
                            wire:click="switchTo{{ $viewMode === 'task' ? 'Context' : 'Task' }}"
                            class="flex items-center gap-1"
                        >
                            <i class="fal fa-eye text-sm"></i>
                            @if($viewMode === 'task')
                                Einsehen
                            @else
                                Zurück zur Aufgabe
                            @endif
                        </x-secondary-button>
                    @endif
    
                    {{-- Übernehmen --}}
                    @if(is_null($task->assigned_to))
                        <x-secondary-button
                            wire:click="assignToMe"
                            class="flex items-center gap-1"
                        >
                            <i class="fal fa-user-plus text-sm"></i>
                            Übernehmen
                        </x-secondary-button>
                    @endif
    
                    {{-- Abschließen --}}
                    @if(
                        $task->status !== \App\Models\AdminTask::STATUS_COMPLETED &&
                        (int) $task->assigned_to === (int) $currentUserId
                    )
                        <x-secondary-button
                            wire:click="markAsCompleted"
                            class="flex items-center gap-1 text-emerald-700 border-emerald-500 hover:bg-emerald-50"
                        >
                            <i class="fal fa-check-circle text-sm"></i>
                            Abschließen
                        </x-secondary-button>
                    @endif
                </div>

                <div class="flex gap-2">


                    {{-- Close --}}
                    <x-secondary-button wire:click="close">
                        Schließen
                    </x-secondary-button>

                </div>
            </div>

        @else
            <x-secondary-button wire:click="close">
                Schließen
            </x-secondary-button>
        @endif
    </x-slot>

</x-modal.modal>
