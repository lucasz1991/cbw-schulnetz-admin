<div x-data="{ open: @entangle('showModal') }">
    <template x-teleport="body">
        <div x-cloak x-show="open" x-transition.opacity
             class="fixed inset-0 z-50 bg-black/30 backdrop-blur-sm p-4 overflow-y-auto">
            <div x-show="open" x-transition
                 class="mx-auto max-w-3xl rounded-2xl bg-white shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            Antrag #{{ $request->id ?? '—' }}
                        </h3>
                        <p class="text-sm text-gray-500">
                            Nutzer: {{ $request?->user?->name ?? '—' }} ·
                            Eingereicht: {{ optional($request?->submitted_at)->format('d.m.Y H:i') ?? '—' }}
                        </p>
                    </div>
                    <button @click="open=false" wire:click="close"
                            class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100">
                        ✕
                    </button>
                </div>

                @if($request)
                    <div class="px-6 py-5 space-y-6">
                        {{-- Meta --}}
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <div class="text-xs uppercase text-gray-500">Typ</div>
                                <div class="font-medium">
                                    @php
                                        $typeMap = [
                                            'absence' => 'Fehlzeit',
                                            'makeup' => 'Nachprüfung',
                                            'external_makeup' => 'Externe Prüfung',
                                            'general' => 'Allgemein',
                                        ];
                                    @endphp
                                    {{ $typeMap[$request->type] ?? $request->type }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-gray-500">Status</div>
                                @php
                                    $statusColors = [
                                        'pending'   => 'bg-amber-100 text-amber-800',
                                        'in_review' => 'bg-blue-100 text-blue-800',
                                        'approved'  => 'bg-green-100 text-green-800',
                                        'rejected'  => 'bg-red-100 text-red-800',
                                        'canceled'  => 'bg-gray-100 text-gray-700',
                                    ];
                                @endphp
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs {{ $statusColors[$request->status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ $request->status_label }}
                                </span>
                            </div>

                            <div>
                                <div class="text-xs uppercase text-gray-500">Klasse</div>
                                <div class="font-medium">{{ $request->class_label ?? $request->class_code ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-gray-500">Modul</div>
                                <div class="font-medium">{{ $request->module_code ?? '—' }}</div>
                            </div>

                            <div>
                                <div class="text-xs uppercase text-gray-500">Zeitraum</div>
                                <div class="font-medium">
                                    @if($request->full_day)
                                        Ganztägig
                                    @else
                                        @php
                                            $df = optional($request->date_from)->format('d.m.Y');
                                            $dt = optional($request->date_to)->format('d.m.Y');
                                        @endphp
                                        @if($df && $dt && $df !== $dt)
                                            {{ $df }} – {{ $dt }}
                                        @elseif($df)
                                            {{ $df }}
                                        @else
                                            —
                                        @endif
                                    @endif
                                </div>
                            </div>

                            <div>
                                <div class="text-xs uppercase text-gray-500">Gebühr</div>
                                <div class="font-medium">{{ $request->fee_formatted ?? '—' }}</div>
                            </div>

                            <div class="sm:col-span-2">
                                <div class="text-xs uppercase text-gray-500">Titel</div>
                                <div class="font-medium">{{ $request->title ?? '—' }}</div>
                            </div>

                            <div class="sm:col-span-2">
                                <div class="text-xs uppercase text-gray-500">Nachricht</div>
                                <div class="prose max-w-none text-sm">{{ $request->message ?? '—' }}</div>
                            </div>
                        </div>

                        {{-- Anhänge --}}
                        <div>
                            <div class="mb-1 text-xs uppercase text-gray-500">Anhänge</div>
                            @if($request->files->count())
                                <ul class="divide-y rounded border">
                                    @foreach($request->files as $file)
                                        <li class="flex items-center justify-between gap-3 px-3 py-2">
                                            <div class="truncate">
                                                <div class="text-sm font-medium truncate">{{ $file->name ?? $file->filename ?? 'Datei' }}</div>
                                                <div class="text-xs text-gray-500">{{ $file->mime ?? '' }} · {{ $file->human_size ?? '' }}</div>
                                            </div>
                                            @if(method_exists($file, 'temporaryPublicUrl'))
                                                <a href="{{ $file->temporaryPublicUrl() }}"
                                                   target="_blank"
                                                   class="text-sm underline underline-offset-2 hover:no-underline">Öffnen</a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="text-sm text-gray-500">Keine Anhänge.</div>
                            @endif
                        </div>

                        <div class="flex justify-end pt-1">
                            <button wire:click="delete"
                                    wire:confirm="Möchten Sie diesen Antrag wirklich löschen? Dieser Vorgang kann nicht rückgängig gemacht werden."
                                    class="rounded-md border px-3 py-1.5 text-sm text-red-700 hover:bg-red-50">
                                Löschen
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </template>
</div>
