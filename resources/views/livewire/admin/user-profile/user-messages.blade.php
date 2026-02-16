<div class="relative w-full">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h3 class="text-xl font-semibold text-gray-800">Nachrichten</h3>

        <input
            type="text"
            wire:model.live.debounce.400ms="search"
            placeholder="Nachrichten durchsuchen ..."
            class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-sky-400 focus:ring-2 focus:ring-sky-200 sm:w-80"
        />
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="border-b bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold">Datum</th>
                    <th class="px-4 py-2 text-left font-semibold">Absender</th>
                    <th class="px-4 py-2 text-left font-semibold">Betreff</th>
                    <th class="px-4 py-2 text-left font-semibold">Nachricht</th>
                    <th class="px-4 py-2 text-right font-semibold">Aktion</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
                @forelse($messages as $msg)
                    @php
                        $isCbwTeam = (int)($msg->from_user ?? 0) === 1;
                        $senderLabel = $isCbwTeam ? 'CBW Team' : ($msg->sender?->name ?? 'System');
                        $isRead = (int)($msg->status ?? 1) === 2;
                    @endphp
                    <tr class="cursor-pointer transition hover:bg-gray-50 {{ $isRead ? '' : 'bg-amber-50/40' }}" wire:dblclick="openMessage({{ $msg->id }})">
                        <td class="whitespace-nowrap px-4 py-3 align-top text-gray-500">
                            {{ $msg->created_at?->format('d.m.Y H:i') }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 align-top">
                            <div class="flex  gap-1">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $isCbwTeam ? 'bg-gray-50 text-gray-700' : 'bg-gray-100 text-gray-700' }}">
                                    <i class="far {{ $isCbwTeam ? 'fa-building' : 'fa-user' }}"></i>
                                    {{ $senderLabel }}
                                </span>
                                <span class="inline-flex items-center justify-center rounded-full p-1 aspect-square text-[11px] font-semibold {{ $isRead ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    <i class="far {{ $isRead ? 'fa-check-circle' : 'fa-envelope' }}"></i>
                                </span>
                            </div>
                        </td>

                        <td class="px-4 py-3 align-top font-semibold text-gray-800">
                            <div class="flex items-center gap-2">
                                @if($msg->files->isNotEmpty())
                                    @php $firstAttachment = $msg->files->first(); @endphp
                                    <button
                                        type="button"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100"
                                        title="Anhang öffnen"
                                        @click="window.dispatchEvent(new CustomEvent('filepool-preview', { detail: { id: {{ $firstAttachment->id }} } }))"
                                    >
                                        <i class="far fa-paperclip text-[11px]"></i>
                                    </button>
                                @endif
                                <span>{{ $msg->subject ?: '(Kein Betreff)' }}</span>
                            </div>
                        </td>

                        <td class="px-4 py-3 align-top text-gray-700">
                            {{ \Illuminate\Support\Str::limit(strip_tags((string)$msg->message), 140) }}
                        </td>

                        <td class="px-4 py-3 text-right align-top">
                            <div class="inline-flex justify-end" wire:key="actions-{{ $msg->id }}">
                                <x-ui.dropdown.anchor-dropdown
                                    align="right"
                                    width="48"
                                    dropdownClasses="mt-1 w-44 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden"
                                    contentClasses="bg-white py-1"
                                    :overlay="false"
                                    :trap="false"
                                    :scrollOnOpen="false"
                                    :offset="6"
                                >
                                    <x-slot name="trigger">
                                        <x-ui.buttons.button-basic :mode="'basic'" :size="'sm'">
                                            <i class="far fa-ellipsis-v"></i>
                                        </x-ui.buttons.button-basic>
                                    </x-slot>

                                    <x-slot name="content">
                                        <x-ui.dropdown.dropdown-link wire:click="openMessage({{ $msg->id }})" class="hover:bg-sky-100 focus:bg-sky-100">
                                            <i class="far fa-eye mr-2"></i>
                                            Anzeigen
                                        </x-ui.dropdown.dropdown-link>

                                        @if(auth()->user()?->isAdmin())
                                            <x-ui.dropdown.dropdown-link
                                                wire:click="deleteMessage({{ $msg->id }})"
                                                onclick="return confirm('Nachricht wirklich löschen?')"
                                                class="hover:bg-rose-100 focus:bg-rose-100 text-rose-700"
                                            >
                                                <i class="far fa-trash-alt mr-2"></i>
                                                Löschen
                                            </x-ui.dropdown.dropdown-link>
                                        @endif
                                    </x-slot>
                                </x-ui.dropdown.anchor-dropdown>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                            Keine eingehenden Nachrichten gefunden.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $messages->links() }}
    </div>

    <x-dialog-modal wire:model="showMessageModal" maxWidth="3xl">
        <x-slot name="title">
            @php
                $detail = $this->selectedMessage;
                $detailIsCbwTeam = $detail && (int)($detail->from_user ?? 0) === 1;
                $detailSender = $detailIsCbwTeam ? 'CBW Team' : ($detail?->sender?->name ?? 'System');
                $detailIsRead = $detail ? ((int)($detail->status ?? 1) === 2) : false;
            @endphp
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-gray-800">
                        {{ $detail?->subject ?: '(Kein Betreff)' }}
                    </div>
                    @if($detail)
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                            {{ $detail->created_at?->format('d.m.Y H:i') }} · {{ $detailSender }}
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $detailIsRead ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                <i class="far {{ $detailIsRead ? 'fa-check-circle' : 'fa-envelope' }}"></i>
                                {{ $detailIsRead ? 'Gelesen' : 'Ungelesen' }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </x-slot>

        <x-slot name="content">
            @if($this->selectedMessage)
                @php
                    $detail = $this->selectedMessage;
                    $detailIsCbwTeam = (int)($detail->from_user ?? 0) === 1;
                    $detailSender = $detailIsCbwTeam ? 'CBW Team' : ($detail->sender?->name ?? 'System');
                    $detailIsRead = (int)($detail->status ?? 1) === 2;
                @endphp

                <div class="space-y-4">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
                        <div class="mb-1 text-xs uppercase tracking-wide text-gray-500">Absender</div>
                        <div class="font-medium text-gray-800">{{ $detailSender }}</div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <div class="mb-2 text-xs uppercase tracking-wide text-gray-500">Nachricht</div>
                        <div class="whitespace-pre-wrap text-sm text-gray-800">{!! (string)$detail->message !!}</div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <div class="mb-2 text-xs uppercase tracking-wide text-gray-500">Anhänge</div>
                        @if($detail->files->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach($detail->files as $file)
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-md border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs text-sky-700 hover:bg-sky-100"
                                        @click="window.dispatchEvent(new CustomEvent('filepool-preview', { detail: { id: {{ $file->id }} } }))"
                                    >
                                        <i class="far fa-paperclip"></i>
                                        {{ $file->name ?? ('Datei #' . $file->id) }}
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="text-sm text-gray-500">Keine Anhänge vorhanden.</div>
                        @endif
                    </div>
                </div>
            @endif
        </x-slot>

        <x-slot name="footer">
            <div class="flex items-center gap-3">
            @if(auth()->user()?->isAdmin() && $selectedMessageId)
                <x-ui.buttons.button-basic
                    :mode="'danger'"
                    :size="'sm'"
                    wire:click="deleteMessage({{ $selectedMessageId }})"
                    onclick="return confirm('Nachricht wirklich löschen?')"
                >
                    <i class="far fa-trash-alt mr-2"></i>
                    Löschen
                </x-ui.buttons.button-basic>
            @endif
            <x-ui.buttons.button-basic :mode="'basic'" :size="'sm'" wire:click="closeMessageModal">
                <i class="far fa-times mr-2"></i>
                Schließen
            </x-ui.buttons.button-basic>
            </div>
        </x-slot>
    </x-dialog-modal>

    <livewire:tools.file-pools.file-preview-modal />

    <div
        wire:loading.delay.class.remove="opacity-0"
        class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 opacity-0 transition-opacity"
    >
        <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2 shadow">
            <span class="loader"></span>
            <span class="text-sm text-gray-700">wird geladen...</span>
        </div>
    </div>
</div>
