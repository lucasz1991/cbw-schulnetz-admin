<div class="space-y-4 transition" wire:loading.class="cursor-wait opacity-50 animate-pulse">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-lg font-semibold">UVS-API Tests</h1>
            <p class="text-sm text-gray-600">Hier kannst du JSON- und CSV-Endpunkte des <code>ApiUvsService</code> live testen.</p>
            @unless($hasConfig)
                <div class="mt-2 text-sm text-red-600">Achtung: UVS API URL/KEY fehlen in den Settings (<code>api.uvs_api_url</code>, <code>api.uvs_api_key</code>).</div>
            @endunless
            <div class="text-xs text-gray-500 mt-1">Base URL: {{ $baseUrl ?: '-' }}</div>
        </div>

        <div class="flex items-center gap-2">
            <label class="inline-flex items-center gap-2 px-3 py-2 rounded border bg-white">
                <input type="checkbox" wire:model.live="useFake" class="rounded">
                <span>Fake-Antworten nutzen</span>
            </label>
            <button class="px-3 py-2 rounded border bg-white" wire:click="runAll" @disabled($running)>
                @if($running) Laeuft... @else Alle Tests ausfuehren @endif
            </button>
            <button class="px-3 py-2 rounded border bg-white" wire:click="clearResults">Ergebnisse leeren</button>
        </div>
    </div>

    <div class="bg-white border rounded-lg p-4">
        <h3 class="text-sm font-semibold mb-3">Test-Parameter</h3>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
            <label class="text-sm">E-Mail
                <input type="email" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="email">
            </label>
            <label class="text-sm">Teilnehmer-ID (Qualiprogram)
                <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="participantId">
            </label>
            <label class="text-sm">Person-ID (z. B. 1-0035645)
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
                    <input type="number" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="limit" min="1" max="50000">
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
                    <option value="">-</option>
                    <option value="asc">asc</option>
                    <option value="desc">desc</option>
                </select>
            </label>
        </div>

        <div class="mt-5 border-t pt-4">
            <h4 class="text-sm font-semibold mb-3">CSV-Export-Filter</h4>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-3">
                <label class="text-sm">Institut ID
                    <input type="number" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="institutId" min="1">
                </label>
                <label class="text-sm">Institut IDs (CSV)
                    <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="institutIds" placeholder="1,2,3">
                </label>
                <label class="text-sm">TN Nummer
                    <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="participantNumber" placeholder="optional">
                </label>
                <label class="text-sm">Kurs / Modul
                    <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="courseFilter" placeholder="z. B. FKBL">
                </label>
                <label class="text-sm">Beratung ID
                    <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="beratungId" placeholder="optional">
                </label>
                <label class="text-sm">Klasse
                    <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="classFilter" placeholder="z. B. BMG51">
                </label>
                <label class="text-sm">Dozent ID
                    <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="teacherId" placeholder="optional">
                </label>
                <label class="text-sm">PLZ
                    <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="plzFilter" placeholder="optional">
                </label>
                <label class="text-sm">VTZ
                    <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="vtzFilter" placeholder="V oder T">
                </label>
                <label class="text-sm">KT kurz
                    <input type="text" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="ktKurzFilter" placeholder="z. B. AA">
                </label>
                <label class="text-sm">Kuendigung
                    <select class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="hasCancellation">
                        <option value="">egal</option>
                        <option value="1">nur mit Kuendigung</option>
                        <option value="0">nur ohne Kuendigung</option>
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-sm">Min Betrag
                        <input type="number" step="0.01" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="minAmount">
                    </label>
                    <label class="text-sm">Max Betrag
                        <input type="number" step="0.01" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="maxAmount">
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-sm">Min Std. Satz
                        <input type="number" step="0.01" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="minStdSatz">
                    </label>
                    <label class="text-sm">Max Std. Satz
                        <input type="number" step="0.01" class="mt-1 w-full border rounded px-3 py-2" wire:model.defer="maxStdSatz">
                    </label>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button class="px-3 py-2 rounded border bg-white" wire:click="$refresh">Parameter uebernehmen</button>
        </div>
    </div>

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
                                        {{ $r['status'] ?? '-' }} - {{ $r['duration'] ?? '-' }} ms
                                    </span>
                                </div>
                                <div class="text-xs text-gray-600 mt-1">{{ $r['message'] ?? '' }}</div>
                                @if(!empty($r['preview']))
                                    <details class="mt-1">
                                        <summary class="text-xs text-gray-500 cursor-pointer">Antwort ansehen</summary>
                                        <div class="mt-1 bg-black text-white p-4 rounded max-h-80 overflow-auto">
                                            <pre class="text-[11px] whitespace-pre leading-5"><code>{{ $r['preview'] }}</code></pre>
                                        </div>
                                    </details>
                                @endif
                                <div class="text-[11px] text-gray-400 mt-1">Stand: {{ $r['timestamp'] ?? '' }}</div>
                            @else
                                <span class="text-xs text-gray-400">Noch nicht ausgefuehrt</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button class="px-2 py-1 rounded border" wire:click="runOne('{{ $t['key'] }}')">Ausfuehren</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white border rounded-lg p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold">SQL Runner (POST /api/sql)</h3>
            <div class="text-xs text-gray-500">
                Nur Leseabfragen (Server blockt INSERT/UPDATE/DELETE/DDL)
            </div>
        </div>

        <label class="text-sm block">
            SQL
            <textarea
                class="mt-1 w-full border rounded px-3 py-2 font-mono text-xs"
                rows="6"
                wire:model.defer="sqlQuery"
                placeholder="SELECT * FROM person WHERE institut_id = 1 ORDER BY nachname"
            ></textarea>
        </label>

        <div class="mt-3 flex items-center gap-2">
            <button class="px-3 py-2 rounded border bg-white" wire:click="runSqlManual" @disabled($running)>
                @if($running) Laeuft... @else SQL ausfuehren @endif
            </button>
            <button class="px-3 py-2 rounded border bg-white" wire:click="clearSqlResult">Ausgabe leeren</button>
        </div>

        @php $r = $results['sql_run'] ?? null; @endphp
        <div class="mt-4">
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
                        {{ $r['status'] ?? '-' }} - {{ $r['duration'] ?? '-' }} ms
                    </span>
                </div>
                <div class="text-xs text-gray-600 mt-1">{{ $r['message'] ?? '' }}</div>

                @if(!empty($r['preview']))
                    <details class="mt-2">
                        <summary class="text-xs text-gray-500 cursor-pointer">Antwort ansehen</summary>
                        <div class="mt-1 bg-black text-white p-4 rounded max-h-80 overflow-auto">
                            <pre class="text-[11px] whitespace-pre leading-5"><code>{{ $r['preview'] }}</code></pre>
                        </div>
                    </details>
                @endif

                <div class="text-[11px] text-gray-400 mt-1">Stand: {{ $r['timestamp'] ?? '' }}</div>
            @else
                <span class="text-xs text-gray-400">Noch keine SQL ausgefuehrt.</span>
            @endif
        </div>
    </div>
</div>
