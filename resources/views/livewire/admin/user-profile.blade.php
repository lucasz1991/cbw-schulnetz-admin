<div  x-data="{ selectedTab: $persist('userDetails') }">
    <div class="mb-4 flex justify-between items-center" >
        <x-back-button />
                <!-- Weitere Optionen mit Dropdown -->
        <div x-data="{ open: false }" class="relative">
            <x-button 
                @click="open = !open" 
                class="btn-xs"
            >
                Optionen
            </x-button>

            <!-- Dropdown-Menü -->
            <div 
                x-show="open" 
                @click.away="open = false" 
                x-cloak 
                class="absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded shadow-lg z-40"
            >
                <ul>
                    <li>
                        @if ($user->status)
                            <button wire:click="deactivateUser()" class="block w-full px-4 py-2 text-left hover:bg-yellow-100">
                                Deaktivieren
                            </button>
                        @else
                            <button wire:click="activateUser()" class="block w-full px-4 py-2 text-left hover:bg-green-100">
                                Aktivieren
                            </button>
                        @endif
                    </li>
                    <li>
                        <button 
                            wire:click="openMailModal({{ $user->id }})" 
                            class="block w-full px-4 py-2 text-gray-700 hover:bg-blue-100 text-left"
                        >
                            Mail senden
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
  <!-- Header-Bild -->
  <div class="rounded-t-lg h-32 overflow-hidden bg-gray-200 relative">
        <!-- Status Badge (links oben) -->
        <div class="absolute top-2 left-2 px-3 py-1 rounded-full text-xs font-semibold text-white" 
             :class="{ 'bg-green-500': {{ $user->isActive() ? 'true' : 'false' }}, 'bg-red-500': {{ !$user->isActive() ? 'true' : 'false' }} }">
            {{ $user->isActive() ? 'Aktiv' : 'Inaktiv' }}
        </div>

        <!-- Erstellungsdatum Badge (rechts oben) -->
        <div class="absolute top-2 right-2 px-3 py-1 rounded-full text-xs font-semibold bg-gray-700 text-white">
            Registriert: {{ $user->created_at->format('d.m.Y') }}
        </div>
    </div>

    <!-- Profilbild -->
    <div class="mx-auto w-32 h-32 relative -mt-16 border-4 border-white rounded-full overflow-hidden">
        <img 
            class="object-cover object-center h-32  aspect-square" 
            src="{{ $user->profile_photo_url ?? 'https://via.placeholder.com/150' }}" 
            alt="{{ $user->name }}"
        >
    </div>

    <!-- Benutzerdetails -->
    <div class="text-center mt-2 mb-6">
        <h2 class="font-semibold text-lg">{{ $user->name }}</h2>
        <!-- Role Badge  -->
        <div class="mt-4 w-max mx-auto px-3 py-1 rounded-full text-xs font-semibold "  x-cloak
             :class="{ 'text-green-600 bg-green-100': {{ $user->role == 'guest'  ? 'true' : 'false' }}, 'text-blue-600 bg-blue-100': {{ $user->role == 'tutor' ? 'true' : 'false' }} }">
            {{ $user->role == 'tutor' ? 'Mitarbeiter' : 'Teilnehmer' }}
        </div>
    </div>




    {{-- Tabs: ersetzt dein bisheriges Tab-Menü + Content-Blöcke --}}
    <x-ui.accordion.tabs
        :tabs="[
            'userDetails' => ['label' => 'Details',   'icon' => 'fad fa-idd-card'],
            'uvsdata'     => ['label' => 'UVS Daten', 'icon' => 'fad fa-database'],
        ]"
        :collapseAt="'md'"
        default="userDetails"
        persist-key="admin.user.{{ $user->id }}.tabs"
        class="mt-6"
    >
        {{-- TAB: Details --}}
        <x-ui.accordion.tab-panel for="userDetails">
  <div class="w-full rounded-xl bg-gradient-to-b from-gray-50 to-gray-100 p-6 shadow-sm ring-1 ring-gray-200/70">

    {{-- Headline --}}
    <div class="mb-6 flex items-center justify-between">
      <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
        <i class="fad fa-id-card text-gray-500"></i>
        Benutzerprofil
      </h2>
    </div>

    {{-- Benutzerkonto Card --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
      <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
        <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
          <i class="fad fa-user text-gray-500"></i>
          Benutzerdetails
        </h3>
        <span class="text-xs font-medium text-gray-500">User-ID: {{ $user->id }}</span>
      </div>

      <div class="px-5 py-5">
        <dl class="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
          {{-- Benutzername --}}
          <div class="sm:col-span-1">
            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Benutzername</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $user->name }}</dd>
          </div>

          {{-- E-Mail + Verifiziert-Badge --}}
          <div class="sm:col-span-1">
            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">E-Mail</dt>
            <dd class="mt-1 flex items-center gap-2 text-sm text-gray-900">
              <span>{{ $user->email }}</span>
              @if($user->email_verified_at)
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700"
                      title="Verifiziert am: {{ $user->email_verified_at->format('d.m.Y H:i') }}">
                  <i class="fad fa-check-circle"></i> verifiziert
                </span>
              @else
                <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700"
                      title="E-Mail nicht verifiziert">
                  <i class="fad fa-exclamation-circle"></i> nicht verifiziert
                </span>
              @endif
            </dd>
          </div>

          {{-- Registriert am --}}
          <div class="sm:col-span-1">
            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Registriert am</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $user->created_at->format('d.m.Y') }}</dd>
          </div>
        </dl>
      </div>
    </div>

    {{-- UVS Personendaten --}}
    @if($user->person)
      <div class="relative mt-6 rounded-xl border border-gray-200 bg-white shadow-sm">
        {{-- Header mit Update-Button --}}
        <div class="flex items-start justify-between border-b border-gray-100 px-5 py-4">
          <div class="flex flex-wrap items-center gap-3">
            <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
              <i class="fad fa-user-cog text-gray-500"></i>
              UVS Personendetails
            </h3>
            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
              <i class="fad fa-clock"></i>
              {{ $user->person->last_api_update?->diffForHumans() ?? $user->person->last_api_update->diffForHumans() }}
            </span>
          </div>

          <button
            wire:click="uvsApiUpdate"
            class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-100 hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-300"
            title="UVS-Daten aktualisieren"
          >
            <i class="fad fa-sync"></i>
            Aktualisieren
          </button>
        </div>

        {{-- Loading-Overlay beim Aktualisieren --}}
        <div wire:loading.delay.class.remove="opacity-0"
             wire:target="uvsApiUpdate"
             class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 opacity-0 transition-opacity">
          <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2 shadow">
            <svg class="h-5 w-5 animate-spin text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <span class="text-sm text-gray-700">UVS-Daten werden aktualisiert…</span>
          </div>
        </div>

        {{-- Inhalt --}}
        <div class="px-5 py-5">
          <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Persönliche Informationen --}}
            <div class="rounded-lg border border-gray-200/70 bg-gray-50 p-4">
              <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700">
                <i class="fad fa-id-badge text-gray-500"></i>
                Persönliche Informationen
              </h4>
              <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Person-ID</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->person_id }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Institut-ID</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->institut_id }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Person-Nr.</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->person_nr }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Status</dt>
                  <dd class="mt-1">
                    <span class="inline-flex items-center gap-1 rounded-md bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 ring-1 ring-sky-200">
                      <i class="fad fa-badge-check"></i> {{ $user->person->status }}
                    </span>
                  </dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Aktualisiert am</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->upd_date }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Titel</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->titel_kennz }}</dd>
                </div>

                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Vorname</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->vorname }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Nachname</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->nachname }}</dd>
                </div>

                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Geschlecht</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->geschlecht }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Nationalität</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->nationalitaet }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Familienstand</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->familien_stand }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Geburtsdatum</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->geburt_datum }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Geburtsname</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->geburt_name }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Geburtsland</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->geburt_land }}</dd>
                </div>
                <div class="sm:col-span-2">
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Geburtsort</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->geburt_ort }}</dd>
                </div>
              </dl>
            </div>

            {{-- Adresse --}}
            <div class="rounded-lg border border-gray-200/70 bg-gray-50 p-4">
              <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700">
                <i class="fad fa-map-marker-alt text-gray-500"></i>
                Adresse
              </h4>
              <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Landeskürzel</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->lkz }}</dd>
                </div>
                <div class="sm:col-span-2">
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Straße</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->strasse }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Adresszusatz 1</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->adresszusatz1 }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Adresszusatz 2</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->adresszusatz2 }}</dd>
                </div>

                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">PLZ</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->plz }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Ort</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->ort }}</dd>
                </div>

                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">PLZ (Postfach)</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->plz_pf }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Postfach</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->postfach }}</dd>
                </div>

                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">PLZ (Geschäftskunde)</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->plz_gk }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">PLZ (alt)</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->plz_alt }}</dd>
                </div>
                <div>
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Ort (alt)</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->ort_alt }}</dd>
                </div>
                <div class="sm:col-span-2">
                  <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Straße (alt)</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ $user->person->strasse_alt }}</dd>
                </div>
              </dl>
            </div>
          </div>
        </div>
      </div>
    @else
      <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
        <i class="fad fa-info-circle mr-2"></i>
        Keine Personendetails verfügbar.
      </div>
    @endif
  </div>
</x-ui.accordion.tab-panel>


        {{-- TAB: UVS Daten --}}
        <x-ui.accordion.tab-panel for="uvsdata">
            <div class="w-full bg-gray-100 shadow rounded-lg p-6 mt-0">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">UVS Daten</h2>
                <div wire:loading>
                    <div class="flex justify-center items-center h-20">
                        <svg class="animate-spin h-5 w-5 text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </div>
                </div>
                {{-- Platz für UVS-Tabellen/Listen --}}
            </div>
        </x-ui.accordion.tab-panel>
    </x-ui.accordion.tabs>



    <livewire:admin.users.messages.message-form />

</div>
