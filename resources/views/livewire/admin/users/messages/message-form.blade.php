<div
    x-data="{ showModal: @entangle('showMailModal') }"
    x-init="$watch('showModal', value => { if (!value) { $wire.handleModalClosed(); } })"
>
    <x-dialog-modal wire:model="showMailModal">
        <x-slot name="title">
            Nachricht verfassen

            @if ($mailUserId)
                <span class="mt-1 block text-sm text-gray-500">
                    An: {{ App\Models\User::find($mailUserId)?->email ?? 'Benutzer nicht gefunden' }}
                </span>
            @elseif (! empty($directRecipients))
                <span class="mt-1 block text-sm text-gray-500">
                    An: {{ implode(', ', $directRecipients) }}
                </span>
            @else
                <span class="mt-1 block text-sm text-gray-500">
                    An {{ count($selectedUsers) }} Benutzer senden
                </span>
            @endif
        </x-slot>

        <x-slot name="content">
            <div class="mb-4 border-l-4 border-yellow-500 bg-yellow-100 p-4 text-yellow-700" role="alert">
                <p class="font-bold">Wichtiger Hinweis</p>
                <p>Bitte prüfe Betreff, Überschrift und Nachricht sorgfältig, bevor du den Versand anstößt.</p>
            </div>

            @if ($forceMailOnly)
                <div class="mb-4 rounded border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                    Für freie Mailadressen wird diese Nachricht nur per E-Mail versendet.
                </div>
            @else
                <div class="mt-4">
                    <label class="inline-flex cursor-pointer items-center">
                        <input type="checkbox" value="" wire:model="mailWithMail" class="peer sr-only">
                        <div class="relative h-6 w-11 rounded-full bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 peer-checked:bg-blue-600 dark:bg-gray-700 dark:peer-focus:ring-blue-800 dark:peer-checked:bg-blue-600 after:absolute after:start-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white rtl:peer-checked:after:-translate-x-full dark:border-gray-600"></div>
                        <span class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300">Auch als E-Mail senden?</span>
                    </label>
                </div>
            @endif

            <div class="mt-4">
                <label for="mailSubject" class="block text-sm font-medium text-gray-700">Betreff</label>
                <input type="text" id="mailSubject" wire:model="mailSubject" class="mt-1 block w-full rounded border px-4 py-2">
                <x-input-error for="mailSubject" class="mt-2" />
            </div>

            <div class="mt-4">
                <label for="mailHeader" class="block text-sm font-medium text-gray-700">Überschrift</label>
                <input type="text" id="mailHeader" wire:model="mailHeader" class="mt-1 block w-full rounded border px-4 py-2">
                <x-input-error for="mailHeader" class="mt-2" />
            </div>

            <div class="mt-4">
                <label for="mailBody" class="block text-sm font-medium text-gray-700">Nachricht</label>
                <textarea id="mailBody" rows="6" wire:model="mailBody" class="mt-1 block w-full rounded border px-4 py-2"></textarea>
                <x-input-error for="mailBody" class="mt-2" />
            </div>

            <div class="mt-4">
                <label for="mailLink" class="block text-sm font-medium text-gray-700">Link (optional)</label>
                <input type="url" id="mailLink" wire:model="mailLink" class="mt-1 block w-full rounded border px-4 py-2">
                <x-input-error for="mailLink" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-ui.filepool.drop-zone :model="'fileUploads'" />
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="resetMailModal" wire:loading.attr="disabled">
                Abbrechen
            </x-secondary-button>

            @php $canSendMail = auth()->user()?->isAdmin(); @endphp

            @if ($canSendMail)
                <x-button wire:click="sendMail" wire:loading.attr="disabled" class="ml-2">
                    Senden
                </x-button>
            @else
                <x-button disabled class="ml-2 cursor-not-allowed pointer-events-none opacity-60">
                    Senden
                </x-button>
            @endif
        </x-slot>
    </x-dialog-modal>
</div>
