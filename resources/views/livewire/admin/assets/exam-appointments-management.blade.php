{{-- exam-appointments-management.blade.php --}}
<div
    class="space-y-6"
    x-data="{
        tab: (() => {
            const params = new URLSearchParams(window.location.search);
            const t = (params.get('tab') || '').toLowerCase();
            return (t === 'extern' || t === 'zert' || t === 'cert') ? 'extern' : 'intern';
        })(),
        setTab(next) {
            this.tab = next;

            const url = new URL(window.location.href);
            url.searchParams.set('tab', next);
            // optional: wenn du moechtest, dass beim Tab-Wechsel immer auf Seite 1 gesprungen wird:
            url.searchParams.delete('page_intern');
            url.searchParams.delete('page_extern');

            window.history.replaceState({}, '', url);
        }
    }"
>
    {{-- Header (im Stil vom Onboarding) --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-secondary text-white grid place-items-center">
                    <i class="fas fa-calendar-check text-sm"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-lg font-semibold text-gray-900 truncate">Pruefungsverwaltung</div>
                    <div class="text-xs text-gray-500">Termine & Zertifizierungen</div>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3 w-full sm:w-auto">
            <div class="relative w-full sm:w-72">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <i class="fas fa-search text-xs"></i>
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Suchen (Raum, Datum, Name)"
                    class="w-full rounded-xl border border-gray-200 bg-white pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                />
            </div>

            <button
                type="button"
                wire:click="create"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-secondary px-4 py-2 text-sm font-semibold text-white hover:bg-secondary-dark focus:outline-none focus:ring-2 focus:ring-gray-900/30"
            >
                <i class="fas fa-plus text-xs"></i>
                Neu
            </button>
        </div>
    </div>

    {{-- Tabs: Interne Termine / Zertifizierungen (URL persist) --}}
    <div class="bg-gray-50 rounded-2xl p-1 ring-1 ring-gray-200">
        <div class="flex w-full">
            <button
                type="button"
                class="flex-1 px-4 py-2 text-sm font-semibold rounded-xl transition-all"
                :class="tab === 'intern' ? 'bg-secondary text-white shadow-sm' : 'bg-white text-gray-700 hover:bg-gray-100'"
                @click="setTab('intern')"
            >
                Termine
            </button>

            <button
                type="button"
                class="flex-1 px-4 py-2 text-sm font-semibold rounded-xl transition-all"
                :class="tab === 'extern' ? 'bg-secondary text-white shadow-sm' : 'bg-white text-gray-700 hover:bg-gray-100'"
                @click="setTab('extern')"
            >
                Zertifizierungen
            </button>
        </div>
    </div>

    {{-- Interne Termine (Tab) --}}
    <section class="space-y-3" x-show="tab === 'intern'" x-cloak x-transition.opacity>
        <div class="flex items-end justify-between gap-3">
            <div>
                <div class="text-sm font-semibold text-gray-900">Termine</div>
            </div>
        </div>

        <div class="rounded-2xl ring-1 ring-gray-200">
            <div class="bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold text-gray-600">
                            <th class="px-4 py-3">Termin</th>
                            <th class="px-4 py-3">Raum</th>
                            <th class="px-4 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200">
                        @forelse($internAppointments as $appointment)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    @if($appointment->first_date)
                                        <div class="flex items-center gap-3">
                                            <div class="h-9 w-9 rounded-xl bg-gray-100 grid place-items-center text-gray-600">
                                                <i class="fas fa-clock text-xs"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">{{ $appointment->first_date->format('d.m.Y') }}</div>
                                                <div class="text-xs text-gray-600">{{ $appointment->first_date->format('H:i') }} Uhr</div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">Kein Termin</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    <div class="inline-flex items-center gap-2">
                                        <i class="fas fa-door-open text-xs text-gray-400"></i>
                                        <span class="text-sm text-gray-800">{{ $appointment->room ?? '—' }}</span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <x-dropdown align="right" width="48">
                                        <x-slot name="trigger">
                                            <button
                                                type="button"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                                                title="Aktionen"
                                            >
                                                &#x22EE;
                                            </button>
                                        </x-slot>

                                        <x-slot name="content">
                                            <x-dropdown-link href="#" wire:click.prevent="edit({{ $appointment->id }})">
                                                <i class="far fa-pen mr-2"></i>
                                                Bearbeiten
                                            </x-dropdown-link>

                                            <x-dropdown-link href="#" wire:click.prevent="delete({{ $appointment->id }})" class="hover:bg-red-50">
                                                <i class="far fa-trash-alt mr-2 text-red-600"></i>
                                                Loeschen
                                            </x-dropdown-link>
                                        </x-slot>
                                    </x-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-8 text-center text-sm text-gray-500" colspan="3">Keine Termine vorhanden.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Separate Pagination: intern --}}
            <div class="border-t border-gray-200 bg-gray-50 px-4 py-3">
                {{ $internAppointments->appends(['tab' => 'intern', 'page_extern' => request('page_extern')])->links() }}
            </div>
        </div>
    </section>

    {{-- Externe Termine (Tab) --}}
    <section class="space-y-3" x-show="tab === 'extern'" x-cloak x-transition.opacity>
        <div>
            <div class="text-sm font-semibold text-gray-900">Zertifizierungen</div>
        </div>

        <div class="rounded-2xl ring-1 ring-gray-200">
            <div class="bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold text-gray-600">
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Preis</th>
                            <th class="px-4 py-3">Pflicht 6W</th>
                            <th class="px-4 py-3">Raum</th>
                            <th class="px-4 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200">
                        @forelse($externAppointments as $appointment)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-xl bg-gray-100 grid place-items-center text-gray-600">
                                            <i class="fas fa-certificate text-xs"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-gray-900 truncate">{{ $appointment->name ?? '—' }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3">
                                    @if(!is_null($appointment->preis))
                                        <span class="text-sm text-gray-800">{{ number_format((float)$appointment->preis, 2, ',', '.') }} €</span>
                                    @else
                                        <span class="text-sm text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    @if($appointment->pflicht_6w_anmeldung)
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">Ja</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">Nein</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    <div class="inline-flex items-center gap-2">
                                        <i class="fas fa-door-open text-xs text-gray-400"></i>
                                        <span class="text-sm text-gray-800">{{ $appointment->room ?? '—' }}</span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <x-dropdown align="right" width="48">
                                        <x-slot name="trigger">
                                            <button
                                                type="button"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                                                title="Aktionen"
                                            >
                                                &#x22EE;
                                            </button>
                                        </x-slot>

                                        <x-slot name="content">
                                            <x-dropdown-link href="#" wire:click.prevent="edit({{ $appointment->id }})">
                                                <i class="far fa-pen mr-2"></i>
                                                Bearbeiten
                                            </x-dropdown-link>

                                            <x-dropdown-link href="#" wire:click.prevent="delete({{ $appointment->id }})" class="hover:bg-red-50">
                                                <i class="far fa-trash-alt mr-2 text-red-600"></i>
                                                Loeschen
                                            </x-dropdown-link>
                                        </x-slot>
                                    </x-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-8 text-center text-sm text-gray-500" colspan="5">Keine externen Termine vorhanden.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Separate Pagination: extern --}}
            <div class="border-t border-gray-200 bg-gray-50 px-4 py-3">
                {{ $externAppointments->appends(['tab' => 'extern', 'page_intern' => request('page_intern')])->links() }}
            </div>
        </div>
    </section>

    {{-- Modal (unveraendert) --}}
    <x-dialog-modal wire:model="showModal" maxWidth="2xl">
        @php
            $typename = $type === 'intern' ? 'Termin' : 'Zertifizierung';
        @endphp

        <x-slot name="title">
            {{ $editingId ? $typename.' bearbeiten' : 'Neu anlegen' }}
        </x-slot>

        <x-slot name="content">
            <div class="space-y-5" x-data="{ tab: @entangle('type') }">
                @if(!$editingId)
                    <div class="bg-gray-50 rounded-xl p-1 ring-1 ring-gray-200">
                        <div class="flex w-full">
                            <button
                                type="button"
                                class="flex-1 px-4 py-2 text-sm font-semibold rounded-lg transition-all"
                                :class="tab === 'intern' ? 'bg-secondary text-white shadow-sm' : 'bg-white text-gray-700 hover:bg-gray-100'"
                                @click="tab = 'intern'"
                            >
                                Termin
                            </button>
                            <button
                                type="button"
                                class="flex-1 px-4 py-2 text-sm font-semibold rounded-lg transition-all"
                                :class="tab === 'extern' ? 'bg-secondary text-white shadow-sm' : 'bg-white text-gray-700 hover:bg-gray-100'"
                                @click="tab = 'extern'"
                            >
                                Zertifizierung
                            </button>
                        </div>
                    </div>
                @endif

                <div class="grid md:grid-cols-2 gap-4">
                    <div class="md:col-span-2" x-show="tab === 'extern'" x-collapse>
                        <label class="block text-sm font-medium text-gray-800">Name</label>
                        <input
                            type="text"
                            class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                            wire:model.defer="name"
                        >
                        @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-800">Raum</label>
                        <input
                            type="text"
                            class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                            wire:model.defer="room"
                        >
                        @error('room') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div x-show="tab === 'intern'" x-collapse>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-800">Datum</label>
                            <input
                                type="date"
                                class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                                wire:model="dates.0.date"
                            >
                            @error('dates.0.date') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-800">Uhrzeit</label>
                            <input
                                type="time"
                                class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                                wire:model="dates.0.time"
                            >
                            @error('dates.0.time') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div x-show="tab === 'extern'" x-collapse class="space-y-3">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-800">Preis</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                                wire:model.defer="preis"
                            >
                            @error('preis') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-center md:items-end">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                                <input type="checkbox" wire:model="pflicht_6w_anmeldung" class="border rounded">
                                Pflicht: 6 Wochen vorher anmelden
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-ui.buttons.button-basic wire:click="$set('showModal', false)" :size="'sm'" :mode="'secondary'" wire:loading.attr="disabled">
                    <i class="fas fa-times mr-2"></i>
                    Abbrechen
                </x-ui.buttons.button-basic>

                <x-ui.buttons.button-basic wire:click="save" :size="'sm'" :mode="'primary'" wire:loading.attr="disabled">
                    <i class="fas fa-check mr-2"></i>
                    Speichern
                </x-ui.buttons.button-basic>
            </div>
        </x-slot>
    </x-dialog-modal>
</div>
