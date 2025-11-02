<div class="w-full relative">
    {{-- Kopfbereich: Titel + Suche --}}
    <div class="flex items-center justify-between mb-4">

        <input type="text"
               wire:model.debounce.400ms="search"
               placeholder="Nachrichten durchsuchen …"
               class="border rounded-md px-3 py-1.5 text-sm w-64 focus:ring-primary-500 focus:border-primary-500" />
    </div>

    {{-- Tabellencontainer --}}
    <div class="overflow-x-auto bg-white border rounded-lg shadow-sm">
        <table class="min-w-full text-sm text-left border-collapse">
            <thead class="bg-gray-100 border-b text-gray-700 uppercase text-xs tracking-wide">
                <tr>
                    <th class="px-4 py-2 font-semibold">Datum</th>
                    <th class="px-4 py-2 font-semibold">Absender</th>
                    <th class="px-4 py-2 font-semibold w-1/4">Betreff</th>
                    <th class="px-4 py-2 font-semibold">Nachricht</th>
                    <th class="px-4 py-2 font-semibold">Anhänge</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
                @forelse($messages as $msg)
                    <tr class="hover:bg-gray-50 transition">
                        {{-- Datum --}}
                        <td class="px-4 py-2 text-gray-500 whitespace-nowrap align-top">
                            {{ $msg->created_at?->format('d.m.Y H:i') }}
                        </td>

                        {{-- Absender --}}
                        <td class="px-4 py-2 text-gray-800 font-medium whitespace-nowrap align-top">
                            {{ $msg->sender?->name ?? 'System' }}
                        </td>

                        {{-- Betreff --}}
                        <td class="px-4 py-2 text-gray-800 font-semibold align-top">
                            {{ $msg->subject ?: '(Kein Betreff)' }}
                        </td>

                        {{-- Nachricht (Kurztext) --}}
                        <td class="px-4 py-2 text-gray-700 align-top">
                            {{ Str::limit(strip_tags($msg->message), 150) }}
                        </td>

                        {{-- Anhänge --}}
                        <td class="px-4 py-2 align-top">
                            @if($msg->files->isNotEmpty())
                                <div class="flex flex-wrap gap-1">
                                    @foreach($msg->files as $file)
                                        <a href="{{ $file->getEphemeralPublicUrl() }}"
                                           target="_blank"
                                           class="inline-flex items-center px-2 py-1 text-xs border rounded bg-gray-100 hover:bg-gray-200 text-gray-700">
                                           📎 {{ $file->name }}
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-gray-400 text-xs">–</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">
                            Keine eingehenden Nachrichten gefunden.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $messages->links() }}
    </div>
                        {{-- Loading-Overlay beim Aktualisieren --}}
                    <div wire:loading.delay.class.remove="opacity-0"
                        class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 opacity-0 transition-opacity">
                        <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2 shadow">
                            <span class="loader"></span>
                            <span class="text-sm text-gray-700">wird geladen…</span>
                        </div>
                    </div>
</div>
