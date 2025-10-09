<div class="space-y-4 transition"  wire:loading.class="cursor-wait opacity-50 animate-pulse">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-lg font-semibold">UVS-API Tests</h1>
            <p class="text-sm text-gray-600">Hier kannst du die Methoden des <code>ApiUvsService</code> live testen.</p>
            @unless($hasConfig)
                <div class="mt-2 text-sm text-red-600">Achtung: UVS API URL/KEY fehlen in den Settings (<code>api.uvs_api_url</code>, <code>api.uvs_api_key</code>).</div>
            @endunless
            <div class="text-xs text-gray-500 mt-1">Base URL: {{ $baseUrl ?: '—' }}</div>
        </div>

        <div class="flex items-center gap-2">
            <label class="inline-flex items-center gap-2 px-3 py-2 rounded border bg-white">
                <input type="checkbox" wire:model.live="useFake" class="rounded">
                <span>Fake-Antworten nutzen</span>
            </label>
            <button class="px-3 py-2 rounded border bg-white" wire:click="runAll" @disabled($running)>
                @if($running) Läuft… @else Alle Tests ausführen @endif
            </button>
            <button class="px-3 py-2 rounded border bg-white" wire:click="clearResults">Ergebnisse leeren</button>
        </div>
    </div>

    {{-- Parameter --}}
    <div class="bg-white border rounded-lg p-4">
        <h3 class="text-sm font-semibold mb-3">Test-Parameter</h3>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
            <label class="text-sm">E-Mail
                <input type="email" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="email">
            </label>
            <label class="text-sm">Teilnehmer-ID (Qualiprogram)
                <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="participantId">
            </label>
            <label class="text-sm">Person-ID (z. B. K-0000007)
                <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="personId">
            </label>
            <label class="text-sm">Kurs/Klasse ID
                <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="courseClassId">
            </label>
            <label class="text-sm">Suche
                <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="searchTerm" placeholder="optional">
            </label>
            <div class="grid grid-cols-3 gap-2">
                <label class="text-sm col-span-1">Limit
                    <input type="number" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="limit" min="1" max="100">
                </label>
                <label class="text-sm col-span-1">Von
                    <input type="date" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="from">
                </label>
                <label class="text-sm col-span-1">Bis
                    <input type="date" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="to">
                </label>
            </div>
            <label class="text-sm">Sort
                <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="sort" placeholder="z. B. bezeichnung">
            </label>
            <label class="text-sm">Order
                <select class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="order">
                    <option value="">—</option>
                    <option value="asc">asc</option>
                    <option value="desc">desc</option>
                </select>
            </label>
        </div>

        <div class="mt-3">
            <button class="px-3 py-2 rounded border bg-white" wire:click="$refresh">Parameter übernehmen</button>
        </div>
    </div>

    {{-- Tests Tabelle --}}
    <div class="bg-white border rounded-lg overflow-hidden">
        <table class="w-full text-sm table-fixed">
            <thead class="bg-gray-50">
            <tr class="text-left">
                <th class="px-4 py-2">Test</th>
                <th class="px-4 py-2">Letztes Ergebnis</th>
                <th class="px-4 py-2 w-40"></th>
            </tr>
            </thead>
            <tbody>
            @foreach($this->testList() as $t)
                @php $r = $results[$t['key']] ?? null; @endphp
                <tr class="border-t align-top">
                    <td class="px-4 py-2">
                        <div class="font-medium">{{ $t['name'] }}</div>
                        <div class="text-xs text-gray-500">{{ $t['key'] }}</div>
                    </td>
                    <td class="px-4 py-2">
                        @if($r)
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs
                                    @class([
                                        'bg-green-100 text-green-700' => $r['ok'] ?? false,
                                        'bg-red-100 text-red-700' => !($r['ok'] ?? false),
                                    ])">
                                    {{ ($r['ok'] ?? false) ? 'OK' : 'Fehler' }}
                                </span>
                                <span class="text-xs text-gray-500">
                                    {{ $r['status'] ?? '—' }} · {{ $r['duration'] ?? '—' }} ms
                                </span>
                            </div>
                            <div class="text-xs text-gray-600 mt-1">{{ $r['message'] ?? '' }}</div>
                            <div>
                                @if(!empty($r['preview']))
                                <details class="mt-1">
                                    <summary class="text-xs text-gray-500 cursor-pointer">Antwort ansehen</summary>
                                    <div class="mt-1 bg-black text-white p-4 rounded max-h-80 overflow-auto">
                                    <pre class="text-[11px] whitespace-pre leading-5"><code class="language-json">{{ $r['preview'] }}</code></pre>
                                    </div>
                                </details>
                                @endif
                            </div>
                            <div class="text-[11px] text-gray-400 mt-1">Stand: {{ $r['timestamp'] ?? '' }}</div>
                        @else
                            <span class="text-xs text-gray-400">Noch nicht ausgeführt</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right">
                        <button class="px-2 py-1 rounded border" wire:click="runOne('{{ $t['key'] }}')">Ausführen</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
