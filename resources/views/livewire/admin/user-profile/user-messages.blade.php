<div class="w-full">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800">
            Nachrichten von/bis {{ $user->name }}
        </h2>

        <input type="text"
               wire:model.debounce.300ms="search"
               placeholder="Suche nach Betreff oder Inhalt..."
               class="border rounded-md px-2 py-1 text-sm w-64 focus:ring-primary-500 focus:border-primary-500" />
    </div>

    <div class="bg-white border rounded-lg shadow divide-y">
        @forelse($messages as $msg)
            <div class="p-3 hover:bg-gray-50 transition">
                <div class="flex justify-between items-start">
                    <div class="flex-1 pr-3">
                        <div class="text-sm font-semibold text-gray-800">
                            {{ $msg->subject ?: '(Kein Betreff)' }}
                        </div>

                        <div class="text-xs text-gray-500 mb-1">
                            Von:
                            <span class="font-medium text-gray-700">{{ $msg->sender?->name ?? 'System' }}</span>
                            →
                            An:
                            <span class="font-medium text-gray-700">{{ $msg->recipient?->name ?? 'Unbekannt' }}</span>
                        </div>

                        <p class="text-sm text-gray-700 whitespace-pre-line">
                            {{ $msg->message }}
                        </p>

                        {{-- Dateien, falls vorhanden --}}
                        @if($msg->files->count() > 0)
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($msg->files as $file)

                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="text-xs text-gray-400 whitespace-nowrap">
                        {{ $msg->created_at?->format('d.m.Y H:i') }}
                    </div>
                </div>
            </div>
        @empty
            <div class="p-4 text-sm text-gray-500 text-center">
                Keine Nachrichten gefunden.
            </div>
        @endforelse
    </div>
</div>
