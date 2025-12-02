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
    <h2 class="font-semibold">Interne Nachprüfungen</h2>
    <div class="overflow-x-auto border rounded">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr class="text-left">
            <th class="p-2">Termin(e)</th>
            <th class="p-2">Raum</th>
            <th class="p-2 w-32">Aktionen</th>
          </tr>
        </thead>
        <tbody>
        @forelse($internAppointments as $a)
          @php
            $first = $a->first_date ?? null;
            $count = is_array($a->dates) ? count($a->dates) : 0;
          @endphp
          <tr class="border-t">
            <td class="p-2">
              @if($first)
                {{ $first->format('d.m.Y H:i') }}
                @if($count > 1)
                  <span class="ml-1 text-xs text-gray-500">
                    (+{{ $count - 1 }} weitere)
                  </span>
                @endif
              @else
                —
              @endif
            </td>
            <td class="p-2">{{ $a->room ?? '' }}</td>
            <td class="p-2">
              <div class="flex items-center gap-2">
                <button class="px-2 py-1 rounded bg-gray-200"
                        wire:click="edit({{ $a->id }})">
                  Bearbeiten
                </button>
                <button class="px-2 py-1 rounded bg-red-600 text-white"
                        wire:click="delete({{ $a->id }})">
                  Löschen
                </button>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td class="p-3 text-gray-500" colspan="3">Keine Einträge.</td>
          </tr>
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
    <h2 class="font-semibold">Externe Zertifizierungen</h2>
    <div class="overflow-x-auto border rounded">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr class="text-left">
            <th class="p-2">Name</th>
            <th class="p-2">Termin(e)</th>
            <th class="p-2">Preis</th>
            <th class="p-2">Pflicht 6W</th>
            <th class="p-2">Raum</th>
            <th class="p-2 w-32">Aktionen</th>
          </tr>
        </thead>
        <tbody>
        @forelse($externAppointments as $a)
          @php
            $first = $a->first_date ?? null;
            $count = is_array($a->dates) ? count($a->dates) : 0;
          @endphp
          <tr class="border-t">
            <td class="p-2">{{ $a->name }}</td>
            <td class="p-2">
              @if($first)
                {{ $first->format('d.m.Y H:i') }}
                @if($count > 1)
                  <span class="ml-1 text-xs text-gray-500">
                    (+{{ $count - 1 }} weitere)
                  </span>
                @endif
              @else
                —
              @endif
            </td>
            <td class="p-2">
              @if(!is_null($a->preis))
                {{ number_format((float)$a->preis, 2, ',', '.') }} €
              @endif
            </td>
            <td class="p-2">
              {{ $a->pflicht_6w_anmeldung ? 'Ja' : 'Nein' }}
            </td>
            <td class="p-2">{{ $a->room ?? '—' }}</td>
            <td class="p-2">
              <div class="flex items-center gap-2">
                <button class="px-2 py-1 rounded bg-gray-200"
                        wire:click="edit({{ $a->id }})">
                  Bearbeiten
                </button>
                <button class="px-2 py-1 rounded bg-red-600 text-white"
                        wire:click="delete({{ $a->id }})">
                  Löschen
                </button>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td class="p-3 text-gray-500" colspan="6">Keine Einträge.</td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </section>

  {{-- Modal --}}
  <x-dialog-modal wire:model="showModal" maxWidth="2xl">
    <x-slot name="title">
      {{ $editingId ? 'Prüfung bearbeiten' : 'Neue Prüfung' }}
    </x-slot>

    <x-slot name="content">
      <div x-data="{ type: @entangle('type') }" class="space-y-4">
        <form wire:submit.prevent="save" class="space-y-4">
          <div class="grid md:grid-cols-2 gap-4">
{{-- Typ --}}
<div>
  <label class="block text-sm mb-1">Typ</label>

  @if($editingId)
    <div class="w-full border rounded px-3 py-2 bg-gray-100 text-gray-700">
      {{ $type === 'extern' ? 'extern' : 'intern' }}
    </div>
    <input type="hidden" wire:model="type">
  @else
    <select class="w-full border rounded px-3 py-2" wire:model="type">
      <option value="intern">intern</option>
      <option value="extern">extern</option>
    </select>
  @endif

  @error('type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
</div>


            {{-- Raum --}}
            <div>
              <label class="block text-sm mb-1">Raum</label>
              <input type="text"
                     class="w-full border rounded px-3 py-2"
                     wire:model.defer="room">
              @error('room') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
          </div>

          {{-- Mehrere Termine --}}
          <div class="space-y-3">
            @foreach($dates as $index => $dt)
              <div class="grid md:grid-cols-3 gap-4 items-end">
                <div>
                  <label class="block text-sm mb-1">Datum</label>
                  <input type="date"
                         class="w-full border rounded px-3 py-2"
                         wire:model="dates.{{ $index }}.date">
                  @error("dates.$index.date") <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                  <label class="block text-sm mb-1">Uhrzeit</label>
                  <input type="time"
                         class="w-full border rounded px-3 py-2"
                         wire:model="dates.{{ $index }}.time">
                  @error("dates.$index.time") <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-2">
                  @if($loop->first)
                    <button type="button"
                            class="px-3 py-2 rounded border"
                            wire:click="addDate">
                      + weiterer Termin
                    </button>
                  @else
                    <button type="button"
                            class="px-3 py-2 rounded bg-red-600 text-white"
                            wire:click="removeDate({{ $index }})">
                      Entfernen
                    </button>
                  @endif
                </div>
              </div>
            @endforeach
          </div>

          {{-- Nur bei EXTERNEN Terminen: Name, Preis, Pflicht 6 Wochen --}}
          <div x-cloak x-collapse x-show="type === 'extern'">
            <div class="grid md:grid-cols-2 gap-4 mt-4">
              {{-- Name (nur extern) --}}
              <div>
                <label class="block text-sm mb-1">Name</label>
                <input type="text"
                       class="w-full border rounded px-3 py-2"
                       wire:model.defer="name">
                @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
              </div>

              {{-- Preis (nur extern) --}}
              <div>
                <label class="block text-sm mb-1">Preis</label>
                <input type="number"
                       step="0.01"
                       min="0"
                       class="w-full border rounded px-3 py-2"
                       wire:model.defer="preis">
                @error('preis') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
              </div>

              {{-- Pflicht 6 Wochen (nur extern) --}}
              <div class="md:col-span-2">
                <label class="inline-flex items-center gap-2">
                  <input type="checkbox"
                         wire:model="pflicht_6w_anmeldung"
                         class="border rounded">
                  <span>Pflicht: 6 Wochen vorher anmelden</span>
                </label>
                @error('pflicht_6w_anmeldung') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
              </div>
            </div>
          </div>
        </form>
      </div>
    </x-slot>

    <x-slot name="footer">
      <div class="flex justify-end gap-2">
        <button type="button"
                class="px-3 py-2 rounded border"
                wire:click="$set('showModal', false)">
          Abbrechen
        </button>
        <button type="button" wire:click="save"
                class="px-3 py-2 rounded bg-blue-600 text-white"
                >
          Speichern
        </button>
      </div>
    </x-slot>
  </x-dialog-modal>
</div>
