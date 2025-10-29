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




<!-- Tab-Menü -->
<ul class="flex w-full text-sm font-medium text-center text-gray-500 bg-gray-100 rounded-lg shadow divide-gray-200">
        <!-- Details Tab -->
        <li class="w-full">
            <button 
                @click="selectedTab = 'userDetails'" 
                :class="{ 'text-blue-600 bg-gray-100 border-b-2 border-blue-600': selectedTab === 'userDetails' }" 
                class="w-full p-4 transition-all duration-200 bg-gray-100 hover:bg-blue-100 hover:text-blue-600 focus:outline-none"
            >
                Details
            </button>
        </li>
        
        <!-- UVS Api Data  Tab -->
        <li class="w-full border-l border-gray-200">
            <button 
                @click="selectedTab = 'uvsdata'" 
                :class="{ 'text-blue-600 bg-white border-b-2 border-blue-600': selectedTab === 'uvsdata' }" 
                class="w-full p-4 transition-all duration-200 bg-gray-100 hover:bg-blue-100 hover:text-blue-600 focus:outline-none"
            >
                UVS Daten
            </button>
        </li>

    </ul>

    <!-- Benutzer- und Kundendetails -->
    <div>
        <div  x-show="selectedTab === 'userDetails'" x-collapse  x-cloak>
            <div class="w-full bg-gray-100 shadow rounded-lg p-6 mt-4">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Benutzerprofil</h2>

                <!-- Benutzerinformationen -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6 bg-white p-4 rounded-lg">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-700">Benutzerdetails</h3>
                        <p><strong>Benutzername:</strong> {{ $user->name }}</p>
                        <p>
                        
                        <div class="col-span-4  flex items-center space-x-2" >
                            <span><strong>E-Mail:</strong> {{ $user->email }}</span>
                            @if($user->email_verified_at)
                                <span 
                                    class="h-2 w-2 rounded-full bg-green-300" 
                                    title="Verifiziert am: {{ $user->email_verified_at->format('d.m.Y H:i') }}">
                                </span>
                            @else
                                <span 
                                    class="h-2 w-2 rounded-full bg-red-300" 
                                    title="E-Mail nicht verifiziert">
                                </span>
                            @endif
                        </div>
                        </p>
                        <p><strong>Registriert am:</strong> {{ $user->created_at->format('d.m.Y') }}</p>
                    </div>

                </div>

                <!-- Personendetails -->
                @if($user->person)
                    <div class="mt-6 bg-white p-4 rounded-lg" wire:loading.class="opacity-50 cursor-wait pointer-events-none">
                        <div class="flex items-start justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-800 mb-6">UVS Personendetails <span class="ml-3 bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">{{ $user->person->last_api_update?->diffForHumans() ?? $user->person->last_api_update->diffForHumans()  }}</span></h3>
                            <button wire:click="uvsApiUpdate" class="text-sm text-blue-600 hover:underline">
                                <svg class="w-4 h-4 inline-block fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M534.6 182.6C547.1 170.1 547.1 149.8 534.6 137.3L470.6 73.3C461.4 64.1 447.7 61.4 435.7 66.4C423.7 71.4 416 83.1 416 96L416 128L256 128C150 128 64 214 64 320C64 337.7 78.3 352 96 352C113.7 352 128 337.7 128 320C128 249.3 185.3 192 256 192L416 192L416 224C416 236.9 423.8 248.6 435.8 253.6C447.8 258.6 461.5 255.8 470.7 246.7L534.7 182.7zM105.4 457.4C92.9 469.9 92.9 490.2 105.4 502.7L169.4 566.7C178.6 575.9 192.3 578.6 204.3 573.6C216.3 568.6 224 556.9 224 544L224 512L384 512C490 512 576 426 576 320C576 302.3 561.7 288 544 288C526.3 288 512 302.3 512 320C512 390.7 454.7 448 384 448L224 448L224 416C224 403.1 216.2 391.4 204.2 386.4C192.2 381.4 178.5 384.2 169.3 393.3L105.3 457.3z"/></svg>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">

                            <!-- Persönliche Informationen -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">Persönliche Informationen</h3>
                                <p><strong>Person-ID:</strong> {{ $user->person->person_id }}</p>
                                <p><strong>Institut-ID:</strong> {{ $user->person->institut_id }}</p>
                                <p><strong>Person-Nr.:</strong> {{ $user->person->person_nr }}</p>
                                <p><strong>Status:</strong> {{ $user->person->status }}</p>
                                <p><strong>Aktualisiert am:</strong> {{ $user->person->upd_date }}</p>
                                <p><strong>Vorname:</strong> {{ $user->person->vorname }}</p>
                                <p><strong>Nachname:</strong> {{ $user->person->nachname }}</p>
                                <p><strong>Geschlecht:</strong> {{ $user->person->geschlecht }}</p>
                                <p><strong>Titel:</strong> {{ $user->person->titel_kennz }}</p>
                                <p><strong>Nationalität:</strong> {{ $user->person->nationalitaet }}</p>
                                <p><strong>Familienstand:</strong> {{ $user->person->familien_stand }}</p>
                                <p><strong>Geburtsdatum:</strong> {{ $user->person->geburt_datum }}</p>
                                <p><strong>Geburtsname:</strong> {{ $user->person->geburt_name }}</p>
                                <p><strong>Geburtsland:</strong> {{ $user->person->geburt_land }}</p>
                                <p><strong>Geburtsort:</strong> {{ $user->person->geburt_ort }}</p>
                            </div>

                            <!-- Adresse -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">Adresse</h3>
                                <p><strong>Landeskürzel:</strong> {{ $user->person->lkz }}</p>
                                <p><strong>Straße:</strong> {{ $user->person->strasse }}</p>
                                <p><strong>Adresszusatz 1:</strong> {{ $user->person->adresszusatz1 }}</p>
                                <p><strong>Adresszusatz 2:</strong> {{ $user->person->adresszusatz2 }}</p>
                                <p><strong>PLZ:</strong> {{ $user->person->plz }}</p>
                                <p><strong>Ort:</strong> {{ $user->person->ort }}</p>
                                <p><strong>PLZ (Postfach):</strong> {{ $user->person->plz_pf }}</p>
                                <p><strong>Postfach:</strong> {{ $user->person->postfach }}</p>
                                <p><strong>PLZ (Geschäftskunde):</strong> {{ $user->person->plz_gk }}</p>
                                <p><strong>PLZ (alt):</strong> {{ $user->person->plz_alt }}</p>
                                <p><strong>Ort (alt):</strong> {{ $user->person->ort_alt }}</p>
                                <p><strong>Straße (alt):</strong> {{ $user->person->strasse_alt }}</p>
                            </div>

                            <!-- Kontakt & Kommunikation -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">Kontakt & Kommunikation</h3>
                                <p><strong>Telefon 1:</strong> {{ $user->person->telefon1 }}</p>
                                <p><strong>Telefon 2:</strong> {{ $user->person->telefon2 }}</p>
                                <p><strong>Telefax:</strong> {{ $user->person->telefax }}</p>
                                <p><strong>E-Mail privat:</strong> {{ $user->person->email_priv }}</p>
                                <p><strong>E-Mail CBW:</strong> {{ $user->person->email_cbw }}</p>
                            </div>

                            <!-- Beschäftigung -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">Beschäftigung</h3>
                                <p><strong>Kostenträger:</strong> {{ $user->person->kostentraeger }}</p>
                                <p><strong>BKZ:</strong> {{ $user->person->bkz }}</p>
                                <p><strong>Kundennr.:</strong> {{ $user->person->kunden_nr }}</p>
                                <p><strong>Stamm-Nr. AA:</strong> {{ $user->person->stamm_nr_aa }}</p>
                                <p><strong>Stamm-Nr. BFD:</strong> {{ $user->person->stamm_nr_bfd }}</p>
                                <p><strong>Stamm-Nr. sonst.:</strong> {{ $user->person->stamm_nr_sons }}</p>
                                <p><strong>Stamm-Nr. KST:</strong> {{ $user->person->stamm_nr_kst }}</p>
                                <p><strong>Org. Zeichen:</strong> {{ $user->person->org_zeichen }}</p>
                                <p><strong>Personal-Nr.:</strong> {{ $user->person->personal_nr }}</p>
                                <p><strong>Kreditor-Nr.:</strong> {{ $user->person->kred_nr }}</p>
                                <p><strong>Angestellt von:</strong> {{ $user->person->angestellt_von }}</p>
                                <p><strong>Angestellt bis:</strong> {{ $user->person->angestellt_bis }}</p>
                            </div>

                            <!-- Sonstiges -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">Sonstiges</h3>
                                <p><strong>Person-KZ:</strong> {{ $user->person->person_kz }}</p>
                                <p><strong>Geb-MMTT:</strong> {{ $user->person->geb_mmtt }}</p>
                            </div>
                        </div>
                    </div>
                @else
                    <p class="text-gray-500">Keine Personendetails verfügbar.</p>
                @endif

            </div>
        </div>
        <div  x-show="selectedTab === 'uvsdata'" x-collapse  x-cloak>
            <div class="w-full bg-gray-100 shadow rounded-lg p-6 mt-4">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">UVS Daten</h2>
                <div wire:loading>
                    <div class="flex justify-center items-center h-20">
                        <svg class="animate-spin h-5 w-5 text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </div>
                </div>

            </div>
        </div>
    </div>



    <livewire:admin.users.messages.message-form />

</div>
