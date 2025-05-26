<x-admin-layout>
    
    <x-slot name="header">
        <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
            {{ __('Administrator Dashboard') }}
        </h2>
        
    </x-slot>




    <div class="pb-12">
        <div class="">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">

                <!-- Quick Actions Section -->
                <div class="mb-12">
                    <h3 class="text-xl font-semibold mb-4 text-gray-800">Schnellaktionen</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <button class="bg-blue-500 hover:bg-blue-600 text-white p-4 rounded-lg shadow-md transition duration-300">
                            Benutzer suchen
                        </button>
                        <button class="bg-blue-500 hover:bg-blue-600 text-white p-4 rounded-lg shadow-md transition duration-300">
                            Etiketten drucken
                        </button>
                        <button class="bg-blue-500 hover:bg-blue-600 text-white p-4 rounded-lg shadow-md transition duration-300">
                            Produkt freigeben
                        </button>
                    </div>
                </div>

                <!-- Cards Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Users Management -->
                    <div class="bg-gray-50 p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold mb-2 text-gray-700">Benutzerverwaltung</h3>
                        <p class="text-gray-500">Verwalten Sie alle registrierten Benutzer und ihre Rollen.</p>
                        <div class="mt-4">
                            <a href="/users" class="text-gray-700 hover:underline">Benutzer ansehen</a>
                        </div>
                    </div>

                    <!-- Products Management -->
                    <div class="bg-gray-50 p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold mb-2 text-gray-700">Produkte verwalten</h3>
                        <p class="text-gray-500">Prüfen und geben Sie Produkte auf der Plattform frei.</p>
                        <div class="mt-4">
                            <a href="/products" class="text-gray-700 hover:underline">Produkte anzeigen</a>
                        </div>
                    </div>

                    <!-- Location Management -->
                    <div class="bg-gray-50 p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold mb-2 text-gray-700">Standorte verwalten</h3>
                        <p class="text-gray-500">Bearbeiten und verwalten Sie alle Standorte für Regale und Verkäufer.</p>
                        <div class="mt-4">
                            <a href="/locations" class="text-gray-700 hover:underline">Standorte ansehen</a>
                        </div>
                    </div>

                    <!-- Sales Overview -->
                    <div class="bg-gray-50 p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold mb-2 text-gray-700">Verkaufsübersicht</h3>
                        <p class="text-gray-500">Erhalten Sie detaillierte Berichte und Statistiken zu Verkäufen und Umsätzen.</p>
                        <div class="mt-4">
                            <a href="/sales" class="text-gray-700 hover:underline">Verkäufe anzeigen</a>
                        </div>
                    </div>

                    <!-- Reviews Management -->
                    <div class="bg-gray-50 p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold mb-2 text-gray-700">Bewertungen verwalten</h3>
                        <p class="text-gray-500">Moderieren Sie Bewertungen und sorgen Sie für Qualität auf der Plattform.</p>
                        <div class="mt-4">
                            <a href="/reviews" class="text-gray-700 hover:underline">Bewertungen anzeigen</a>
                        </div>
                    </div>

                    <!-- Shelf Rentals Overview -->
                    <div class="bg-gray-50 p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold mb-2 text-gray-700">Regalmieten überwachen</h3>
                        <p class="text-gray-500">Verwalten und überwachen Sie Regalbuchungen und Mietverträge.</p>
                        <div class="mt-4">
                            <a href="/shelf-rentals" class="text-gray-700 hover:underline">Regalmieten anzeigen</a>
                        </div>
                    </div>
                </div>

                <!-- Statistics Overview Section -->
                <div class="mt-10">
                    <h3 class="text-xl font-semibold mb-4 text-gray-800">Statistiken und Berichte</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Users -->
                        <div class="bg-gray-50 p-6 rounded-lg shadow-md text-center">
                            <h4 class="text-3xl font-bold text-gray-800">{{ $totalUsers ?? 0 }}</h4>
                            <p class="text-gray-500 mt-2">Benutzer insgesamt</p>
                        </div>
                        <!-- Products -->
                        <div class="bg-gray-50 p-6 rounded-lg shadow-md text-center">
                            <h4 class="text-3xl font-bold text-gray-800">{{ $totalProducts ?? 0 }}</h4>
                            <p class="text-gray-500 mt-2">Produkte insgesamt</p>
                        </div>
                        <!-- Sales -->
                        <div class="bg-gray-50 p-6 rounded-lg shadow-md text-center">
                            <h4 class="text-3xl font-bold text-gray-800">{{ $totalSales ?? 0 }}</h4>
                            <p class="text-gray-500 mt-2">Verkäufe insgesamt</p>
                        </div>
                        <!-- Locations -->
                        <div class="bg-gray-50 p-6 rounded-lg shadow-md text-center">
                            <h4 class="text-3xl font-bold text-gray-800">{{ $totalLocations ?? 0 }}</h4>
                            <p class="text-gray-500 mt-2">Standorte insgesamt</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Section -->
                <div class="mt-12">
                    <h3 class="text-xl font-semibold mb-4 text-gray-800">Aktuelle Aktivitäten</h3>
                    <div class="bg-gray-50 p-6 rounded-lg shadow-md">
                        <ul class="divide-y divide-gray-200">
                            <li class="py-4">
                                <span class="font-semibold">Neuer Benutzer registriert:</span> {{ $recentUser ?? 'Max Mustermann' }}
                            </li>
                            <li class="py-4">
                                <span class="font-semibold">Neues Produkt hinzugefügt:</span> {{ $recentProduct ?? 'Produkt XYZ' }}
                            </li>
                            <li class="py-4">
                                <span class="font-semibold">Neue Bewertung:</span> {{ $recentReview ?? 'Gute Qualität, empfehlenswert!' }}
                            </li>
                            <li class="py-4">
                                <span class="font-semibold">Regalbuchung abgeschlossen:</span> {{ $recentRental ?? 'Standort ABC' }}
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Utilization Analysis Section -->
                <div class="mt-12">
                    <h3 class="text-xl font-semibold mb-4 text-gray-800">Auslastungsanalysen</h3>
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <p class="text-gray-500 mb-4">Hier sehen Sie eine Übersicht der aktuellen Auslastung der Standorte und Regale.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gray-100 p-4 rounded-lg shadow-md text-center">
                                <h4 class="text-2xl font-bold text-gray-800">{{ $occupiedShelves ?? 0 }}</h4>
                                <p class="text-gray-500 mt-2">Belegte Regale</p>
                            </div>
                            <div class="bg-gray-100 p-4 rounded-lg shadow-md text-center">
                                <h4 class="text-2xl font-bold text-gray-800">{{ ($totalShelves ?? 0) - ($occupiedShelves ?? 0) }}</h4>
                                <p class="text-gray-500 mt-2">Freie Regale</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-admin-layout>
