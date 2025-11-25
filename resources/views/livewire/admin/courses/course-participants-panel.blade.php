<div class="space-y-3">
    @if($rows->isEmpty())
        <div class="text-sm text-neutral-400">Keine Teilnehmer vorhanden.</div>

    @else
        <div class="text-xs text-neutral-500 mb-1">
            Teilnehmer – Materialbestätigung & Prüfungsergebnis
        </div>

        <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Teilnehmer</th>
                        <th class="px-3 py-2 text-left font-semibold w-40">Material</th>
                        <th class="px-3 py-2 text-left font-semibold w-56">Prüfung</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-neutral-100">
                    @foreach($rows as $row)
                        @php
                            $p = $row['person'];
                            $confirm = $row['has_confirmation'];
                            $confirmAt = $row['confirmation_at'];
                            $examLabel = $row['exam_label'];
                            $examState = $row['exam_state'];
                        @endphp

                        <tr class="hover:bg-neutral-50/60">
                            {{-- Teilnehmer --}}
                            <td class="px-3 py-2 align-top">
                                <x-user.public-info :person="$p" />
                            </td>

                            {{-- Materialbestätigung --}}
                            <td class="px-3 py-2 align-top">
                                @if($confirm)
                                    <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 text-[11px]">
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

                                    <div class="inline-flex items-center gap-2 rounded-full border px-2 py-0.5 text-[11px] {{ $examClasses }}">
                                        <i class="{{ $icon }} text-[11px]"></i>
                                        <span class="truncate max-w-[180px]">{{ $examLabel }}</span>
                                    </div>
                                @else
                                    <div class="inline-flex items-center gap-2 rounded-full bg-neutral-50 text-neutral-500 border border-neutral-200 px-2 py-0.5 text-[11px]">
                                        <i class="fal fa-minus-circle text-[11px]"></i>
                                        <span>kein Ergebnis</span>
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
