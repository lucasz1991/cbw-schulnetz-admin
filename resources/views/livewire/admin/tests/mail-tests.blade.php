<div class="space-y-4 transition" wire:loading.class="cursor-wait opacity-50 animate-pulse">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-lg font-semibold">Mail Tests</h1>
            <p class="text-sm text-gray-600">Teste den Mailversand direkt an eine frei eingegebene Mailadresse.</p>
            <div class="mt-1 text-xs text-gray-500">
                Mailer: {{ $defaultMailer ?: '-' }}
                | Transport: {{ $transport ?: '-' }}
                | From: {{ $fromAddress ?: '-' }}{{ $fromName ? ' (' . $fromName . ')' : '' }}
            </div>
        </div>

        <a href="{{ route('admin.mails') }}" class="rounded border bg-white px-3 py-2 text-sm">
            Zur Mailverwaltung
        </a>
    </div>

    <div class="rounded-lg border bg-white p-4">
        <h3 class="mb-3 text-sm font-semibold">Testadresse</h3>

        <form wire:submit="openMailComposer" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
            <div>
                <label for="mail-test-email" class="text-sm">Mailadresse</label>
                <input
                    id="mail-test-email"
                    type="email"
                    wire:model.defer="email"
                    class="mt-1 w-full rounded border px-3 py-2"
                    placeholder="test@example.org"
                >
                <x-input-error for="email" class="mt-2" />
            </div>

            <div class="flex items-end">
                <button type="submit" class="rounded border bg-white px-3 py-2 text-sm">
                    Mail-Modal öffnen
                </button>
            </div>
        </form>

        <div class="mt-3 rounded border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
            Das vorhandene Mail-Modal wird wiederverwendet. Für freie Mailadressen wird nur E-Mail-Versand angelegt, keine interne Nachricht.
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border bg-white">
        <div class="border-b bg-gray-50 px-4 py-3">
            <h3 class="text-sm font-semibold">Letzte Testmails</h3>
        </div>

        <table class="w-full table-fixed text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="px-4 py-2">Zeitpunkt</th>
                    <th class="px-4 py-2">Empfänger</th>
                    <th class="px-4 py-2">Betreff</th>
                    <th class="px-4 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentMails as $mail)
                    @php
                        $recipients = collect(is_array($mail->recipients) ? $mail->recipients : []);
                        $emails = $recipients->pluck('email')->filter()->values();
                    @endphp
                    <tr class="border-t align-top">
                        <td class="px-4 py-2 text-xs text-gray-500">
                            {{ $mail->created_at?->format('d.m.Y H:i:s') ?? '-' }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="break-all">{{ $emails->join(', ') ?: '-' }}</div>
                        </td>
                        <td class="px-4 py-2">
                            {{ data_get($mail->content, 'subject', '-') }}
                        </td>
                        <td class="px-4 py-2">
                            <span
                                class="@class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs',
                                    'bg-green-100 text-green-700' => $mail->status,
                                    'bg-yellow-100 text-yellow-700' => ! $mail->status,
                                ])"
                            >
                                {{ $mail->status ? 'Verarbeitet' : 'Offen' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-sm text-gray-500">
                            Noch keine Testmails vorhanden.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <livewire:admin.users.messages.message-form :key="'mail-tests-message-form'" />
</div>
