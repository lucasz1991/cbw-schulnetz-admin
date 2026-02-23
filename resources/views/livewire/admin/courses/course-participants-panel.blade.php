<div class="space-y-3">
    @if($rows->isEmpty())
        <div class="text-sm text-neutral-400">
            Keine Teilnehmer vorhanden.
        </div>
    @else
        <div class="text-xs text-neutral-500 mb-1">
            Teilnehmer – Bildungsmittel-Bestätigungen, Prüfungsergebnisse & Kursbewertungen
        </div>

        <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">
                            Teilnehmer
                        </th>
                        <th class="px-3 py-2 text-left font-semibold w-40">
                            Bildungsmittel&nbsp;Bestätigungen
                        </th>
                        <th class="px-3 py-2 text-left font-semibold w-56">
                            Prüfungsergebnisse
                        </th>
                        <th class="px-3 py-2 text-left font-semibold w-48">
                            Kursbewertung
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-neutral-100">
                    @foreach($rows as $row)
    @php
        $p          = $row['person'];
        $confirm    = $row['has_confirmation'];
        $confirmAt  = $row['confirmation_at'];
        $examLabel  = $row['exam_label'];
        $examState  = $row['exam_state'];

        /** @var \App\Models\CourseRating|null $rating */
        $rating     = $row['rating'] ?? null;
        $ratingAvg  = $row['rating_avg'] ?? null;
        $ratingAt   = $row['rating_at'] ?? null;
    @endphp

                        <tr class="hover:bg-neutral-50/60">
                            {{-- Teilnehmer --}}
                            <td class="px-3 py-2 align-top">
                                <div class="flex items-start justify-between gap-2">
                                    <x-user.public-info :person="$p" />
                                    @if(Auth::user()->isAdmin())
                                    <x-dropdown align="right" width="48">
                                        <x-slot name="trigger">
                                            <button type="button" class="text-center px-2 py-1 text-lg font-semibold bg-white hover:bg-gray-100 rounded-lg border border-gray-200">
                                                &#x22EE;
                                            </button>
                                        </x-slot>

                                        <x-slot name="content">
                                            <x-dropdown-link href="#" wire:click.prevent="triggerPersonApiUpdate({{ (int) $p->id }})" class="hover:bg-blue-100">
                                                <i class="far fa-sync mr-2"></i>
                                                Person API Update starten
                                            </x-dropdown-link>
                                        </x-slot>
                                    </x-dropdown>
                                    @endif
                                </div>
                            </td>

                            {{-- Materialbestätigung --}}
                            <td class="px-3 py-2 align-top">
                                @if($confirm)
                                    <div
                                        class="inline-flex items-center gap-2 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 text-[11px]"
                                        @if($confirmAt)
                                            title="Bestätigt am {{ $confirmAt->format('d.m.Y H:i') }}"
                                        @endif
                                    >
                                        <i class="fal fa-check-circle text-[11px]"></i>
                                        <span>bestätigt</span>
                                    </div>
                                @else
                                    <div class="inline-flex items-center gap-2 rounded-full bg-red-50 text-red-700 border border-red-200 px-2 py-0.5 text-[11px]">
                                        <i class="fal fa-exclamation-circle text-[11px]"></i>
                                        <span>fehlt</span>
                                    </div>
                                @endif
                            </td>

                            {{-- Prüfungsergebnis --}}
                            <td class="px-3 py-2 align-top">
                                @if($examLabel)
                                    @php
                                        $examClasses = match($examState) {
                                            'passed'  => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'failed'  => 'bg-red-50 text-red-700 border-red-200',
                                            default   => 'bg-slate-50 text-slate-700 border-slate-200',
                                        };

                                        $icon = match($examState) {
                                            'passed'  => 'fal fa-check-circle',
                                            'failed'  => 'fal fa-times-circle',
                                            default   => 'fal fa-clipboard-check',
                                        };
                                    @endphp

                                    <div class="inline-flex max-w-full items-center gap-2 rounded-full border px-2 py-0.5 text-[11px] {{ $examClasses }}">
                                        <i class="{{ $icon }} text-[11px] shrink-0"></i>
                                        <span class="truncate max-w-[180px]">
                                            {{ $examLabel }}
                                        </span>
                                    </div>
                                @else
                                    <div class="inline-flex items-center gap-2 rounded-full bg-neutral-50 text-neutral-500 border border-neutral-200 px-2 py-0.5 text-[11px]">
                                        <i class="fal fa-minus-circle text-[11px]"></i>
                                        <span>kein Ergebnis</span>
                                    </div>
                                @endif
                            </td>


        {{-- Kursbewertung --}}
        <td class="px-3 py-2 align-top">
            @if($rating && $ratingAvg !== null)
                @php
                    $rounded = number_format($ratingAvg, 1, ',', '.');
                @endphp

                <div
                    class="inline-flex items-center gap-2 rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 text-[11px]"
                    @if($ratingAt)
                        title="Zuletzt bewertet am {{ $ratingAt->format('d.m.Y H:i') }}"
                    @endif
                >
                    <i class="fal fa-star-half-alt text-[11px]"></i>
                    <span>{{ $rounded }} / 5</span>
                </div>
            @else
                <div class="inline-flex items-center gap-2 rounded-full bg-neutral-50 text-neutral-500 border border-neutral-200 px-2 py-0.5 text-[11px]">
                    <i class="fal fa-minus-circle text-[11px]"></i>
                    <span>keine Bewertung</span>
                </div>
            @endif
        </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
