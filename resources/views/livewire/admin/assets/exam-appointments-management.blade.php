<div class="space-y-6">
  {{-- Header --}}
  <div class="flex items-center justify-between gap-3">
    <h1 class="text-xl font-semibold">Prüfungsverwaltung</h1>
    <div class="flex items-center gap-2">
      <input
        type="text"
        wire:model.debounce.300ms="search"
        placeholder="Suchen (Name, Typ, Raum)…"
        class="border rounded px-3 py-2"
      />
      <button wire:click="create" class="px-3 py-2 rounded bg-blue-600 text-white">
        Neuer Termin
      </button>
    </div>
  </div>

  {{-- Interne Termine --}}
  <section class="space-y-3">
    <h2 class="font-semibold">Interne Termine</h2>
    <div class="overflow-x-auto border rounded">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr class="text-left">
            <th class="p-2">Name</th>
            <th class="p-2">Typ</th>
            <th class="p-2">Termin</th>
            <th class="p-2">Preis</th>
            <th class="p-2">Pflicht 6W</th>
            <th class="p-2">Raum</th>
            <th class="p-2 w-32">Aktionen</th>
          </tr>
        </thead>
        <tbody>
        @forelse($internAppointments as $a)
          <tr class="border-t">
            <td class="p-2">{{ $a->name }}</td>
            <td class="p-2">{{ $a->type }}</td>
            <td class="p-2">
              {{ \Illuminate\Support\Carbon::parse($a->termin)->format('d.m.Y H:i') }}
            </td>
            <td class="p-2">
              @if(!is_null($a->preis)) {{ number_format((float)$a->preis, 2, ',', '.') }} € @endif
            </td>
            <td class="p-2">
              {{ $a->pflicht_6w_anmeldung ? 'Ja' : 'Nein' }}
            </td>
            <td class="p-2">{{ $a->room ?? '—' }}</td>
            <td class="p-2">
              <div class="flex items-center gap-2">
                <button class="px-2 py-1 rounded bg-gray-200" wire:click="edit({{ $a->id }})">Bearbeiten</button>
                <button class="px-2 py-1 rounded bg-red-600 text-white" wire:click="delete({{ $a->id }})">Löschen</button>
              </div>
            </td>
          </tr>
        @empty
          <tr><td class="p-3 text-gray-500" colspan="7">Keine Einträge.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div>
      {{ $internAppointments->links() }}
    </div>
  </section>

  {{-- Externe Termine --}}
  <section class="space-y-3">
    <h2 class="font-semibold">Externe Termine</h2>
    <div class="overflow-x-auto border rounded">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr class="text-left">
            <th class="p-2">Name</th>
            <th class="p-2">Typ</th>
            <th class="p-2">Termin</th>
            <th class="p-2">Preis</th>
            <th class="p-2">Pflicht 6W</th>
            <th class="p-2">Raum</th>
            <th class="p-2 w-32">Aktionen</th>
          </tr>
        </thead>
        <tbody>
        @forelse($externAppointments as $a)
          <tr class="border-t">
            <td class="p-2">{{ $a->name }}</td>
            <td class="p-2">{{ $a->type }}</td>
            <td class="p-2">
              {{ \Illuminate\Support\Carbon::parse($a->termin)->format('d.m.Y H:i') }}
            </td>
            <td class="p-2">
              @if(!is_null($a->preis)) {{ number_format((float)$a->preis, 2, ',', '.') }} € @endif
            </td>
            <td class="p-2">
              {{ $a->pflicht_6w_anmeldung ? 'Ja' : 'Nein' }}
            </td>
            <td class="p-2">{{ $a->room ?? '—' }}</td>
            <td class="p-2">
              <div class="flex items-center gap-2">
                <button class="px-2 py-1 rounded bg-gray-200" wire:click="edit({{ $a->id }})">Bearbeiten</button>
                <button class="px-2 py-1 rounded bg-red-600 text-white" wire:click="delete({{ $a->id }})">Löschen</button>
              </div>
            </td>
          </tr>
        @empty
          <tr><td class="p-3 text-gray-500" colspan="7">Keine Einträge.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div>
      {{ $externAppointments->links(data: ['pageName' => 'externPage']) }}
      {{-- In Livewire v3 reicht der unterschiedliche pageName in paginate();
           falls dein Paginator Helper das nicht nimmt, einfach standardmäßig {{ $externAppointments->links() }} lassen. --}}
    </div>
  </section>

<x-dialog-modal wire:model="showModal" maxWidth="2xl">
  <x-slot name="title">
    {{ $editingId ? 'Prüfung bearbeiten' : 'Neue Prüfung' }}
  </x-slot>

  <x-slot name="content">
    <form wire:submit.prevent="save" class="space-y-4">
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">Typ</label>
          <select class="w-full border rounded px-3 py-2" wire:model="type">
            <option value="intern">intern</option>
            <option value="extern">extern</option>
          </select>
          @error('type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
          <label class="block text-sm mb-1">Name</label>
          <input type="text" class="w-full border rounded px-3 py-2" wire:model.defer="name">
          @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
          <label class="block text-sm mb-1">Datum</label>
          <input type="date" class="w-full border rounded px-3 py-2" wire:model="date">
          @error('date') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
          <label class="block text-sm mb-1">Uhrzeit</label>
          <input type="time" class="w-full border rounded px-3 py-2" wire:model="time">
          @error('time') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
          <label class="block text-sm mb-1">Preis</label>
          <input type="number" step="0.01" min="0" class="w-full border rounded px-3 py-2" wire:model.defer="preis">
          @error('preis') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
          <label class="block text-sm mb-1">Raum</label>
          <input type="text" class="w-full border rounded px-3 py-2" wire:model.defer="room">
          @error('room') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" wire:model="pflicht_6w_anmeldung" class="border rounded">
            <span>Pflicht: 6 Wochen vorher anmelden</span>
          </label>
          @error('pflicht_6w_anmeldung') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
      </div>
    </form>
  </x-slot>

  <x-slot name="footer">
    <div class="flex justify-end gap-2">
      <button type="button" class="px-3 py-2 rounded border" wire:click="$set('showModal', false)">
        Abbrechen
      </button>
      <button type="button" class="px-3 py-2 rounded bg-blue-600 text-white" wire:click="save">
        Speichern
      </button>
    </div>
  </x-slot>
</x-dialog-modal>

</div>
