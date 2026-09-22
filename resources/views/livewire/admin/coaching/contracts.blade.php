<div class="px-2 space-y-4" wire:loading.class="opacity-75">
    <div class="flex flex-wrap justify-between items-center gap-3"><h1 class="text-2xl font-bold text-gray-700">Einzelcoaching</h1><x-ui.buttons.button-basic wire:click="synchronize" wire:loading.attr="disabled">Mit UVS abgleichen</x-ui.buttons.button-basic></div>
    <p class="text-sm text-gray-600">Hier erscheinen nur Einzelcoachings mit aktivierter Schulnetz-Einstellung im UVS. Der gesamte Umfang wird vor Beginn beidseitig terminiert. Die Durchführung erfolgt anschließend als normaler Baustein.</p>
    @if(session('coaching_status'))<div role="status" class="p-3 bg-blue-50 text-blue-800 rounded-lg">{{ session('coaching_status') }}</div>@endif
    @if($errors->any())<div role="alert" class="p-3 bg-red-50 text-red-800 rounded-lg">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <label class="block text-sm max-w-md">Vertrag suchen<input type="search" wire:model.live.debounce.400ms="search" class="block w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-200" placeholder="Bezeichnung, Beratung oder UVS-Person"></label>
    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto"><table class="w-full text-sm text-left"><thead class="bg-gray-50 text-gray-600"><tr><th class="p-3">Vertrag / Teilnehmer</th><th class="p-3">Umfang</th><th class="p-3">Dozent</th><th class="p-3">Gesamtplan</th><th class="p-3">Aktion</th></tr></thead><tbody>
    @forelse($contracts as $contract)<tr class="border-t align-top" wire:key="coaching-{{ $contract->id }}">
        <td class="p-3"><strong>{{ $contract->title }}</strong><p>UVS {{ $contract->uvs_contract_id }} · Institut {{ $contract->institut_id }}</p><p>{{ $contract->participant ? trim($contract->participant->vorname.' '.$contract->participant->nachname) : $contract->uvs_person_id }}</p>@if(!$contract->participant?->user_id)<p class="text-amber-700">Teilnehmerkonto noch nicht verknüpft</p>@endif</td>
        <td class="p-3">{{ $contract->agreed_minutes / $contract->unit_minutes }} UE<p class="text-xs text-gray-500">je {{ $contract->unit_minutes }} Minuten</p></td>
        <td class="p-3">{{ $contract->tutor ? trim($contract->tutor->vorname.' '.$contract->tutor->nachname) : ($contract->uvs_tutor_person_id ?: 'Noch nicht ausgewählt') }}
            <p class="text-xs text-gray-500">Auswahl im UVS-Vertrag</p>
            @if($contract->tutor_notified_at)<p class="text-xs text-green-700">Mitteilung im Schulnetz zugestellt</p>
            @elseif(!$contract->tutor?->user_id)<p class="text-xs text-amber-700">Dozentenkonto noch nicht verknüpft</p>
            @else<p class="text-xs text-amber-700">Mitteilung beim nächsten Abgleich ausstehend</p>@endif
            @if($contract->pending_notices_count)<p class="text-xs text-amber-700">{{ $contract->pending_notices_count }} Versandvorgänge offen (E-Mail oder Registrierung / Mitteilung).</p>@foreach($contract->notices->unique('recipient_role') as $notice)<p class="text-xs text-amber-700">{{ $notice->recipient_role === 'tutor' ? 'Dozent' : 'Teilnehmer' }}: {{ $notice->last_error }}</p>@endforeach @endif
        </td>
        <td class="p-3">{{ $contract->planning_label }}<p class="text-xs text-gray-500">Version {{ $contract->revision }} · {{ count($contract->latestPlan?->items ?? []) }} Termine</p>@if($contract->latestPlan)<p class="text-xs">Dozent {{ $contract->latestPlan->tutor_confirmed_at ? '✓' : 'offen' }} · Teilnehmer {{ $contract->latestPlan->participant_confirmed_at ? '✓' : 'offen' }}</p>@endif@if($pending->has($contract->id))<p class="text-amber-700">{{ $pending[$contract->id]->last_error ?? 'UVS-Rückmeldung ausstehend' }}</p>@endif</td>
        <td class="p-3 space-y-2">@if($contract->course_id)<x-ui.buttons.button-basic size="sm" href="{{ route('admin.courses.show', $contract->course_id) }}">Baustein</x-ui.buttons.button-basic>@endif @if($pending->has($contract->id) || $contract->pending_notices_count)<x-ui.buttons.button-basic size="sm" wire:click="retry({{ $contract->id }})" wire:loading.attr="disabled">Erneut abgleichen</x-ui.buttons.button-basic>@endif</td>
    </tr>@empty<tr><td colspan="5" class="p-4">Noch keine Einzelcoachings vorhanden. Im UVS einen Vertrag für das neue System aktivieren und hier abgleichen.</td></tr>@endforelse
    </tbody></table></div>
    {{ $contracts->links() }}
</div>
