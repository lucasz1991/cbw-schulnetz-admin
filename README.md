MiniFinds Admin Panel

Dieses Repository enthält das Admin-Panel für MiniFinds, eine Laravel-Livewire-Anwendung zur Verwaltung eines Dauerflohmarkts. Das Panel ermöglicht Administratoren die Verwaltung von Verkäufern, Regalmieten, Produkten, Rabatten, Auszahlungen und weiteren administrativen Aufgaben.

Features

Regalbuchungen verwalten: Anzeigen, Bearbeiten und Reduzieren von Regalmieten

Produktverwaltung: Übersicht und Kontrolle über eingestellte Produkte

Rabatt-System: Möglichkeit zur Reduzierung von Regalmieten um 25% oder 50%

Auszahlungen: Anfordern und Bearbeiten von Verkäuferauszahlungen

Live-Statistiken: Animierte Diagramme mit ApexCharts für Umsatz- und Nutzerstatistiken

Benachrichtigungen: Follower-Updates, Nachrichten an Verkäufer

Massenmails: Versand von individuellen und automatisierten Mails

Sicherheitsfunktionen: Aktivitätsüberwachung und Nutzerverwaltung

Installation

Voraussetzungen

PHP 8.x

Composer

Node.js & npm

MySQL oder eine kompatible Datenbank

Laravel 10

Livewire 3

Setup

Repository klonen

git clone https://github.com/dein-repository/minifinds-admin.git
cd minifinds-admin

Abhängigkeiten installieren

composer install
npm install && npm run build

Umgebungsvariablen konfigurieren

cp .env.example .env
php artisan key:generate

Passe die .env-Datei an (Datenbankverbindung, API-Keys etc.).

Datenbank migrieren & seeden

php artisan migrate --seed

Lokalen Server starten

php artisan serve

Deployment

Für das Deployment auf einem Live-Server:

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

Stelle sicher, dass der Server Supervisor oder einen ähnlichen Prozessmanager für Queues nutzt.

Admin-Zugang

Nach der Installation existiert ein Standard-Admin-Konto:

E-Mail: admin@minifinds.de

Passwort: password

Ändere das Passwort nach dem ersten Login!

Befehle & Cronjobs

Wichtige Artisan-Befehle:

Queues verarbeiten:

php artisan queue:work

Geplante Aufgaben ausführen:

php artisan schedule:run

Admin-Tasks & Reports generieren:

php artisan admin:tasks

API & Integrationen

PayPal API für Verkäuferauszahlungen

ApexCharts für animierte Statistiken

Cookiebot für DSGVO-konforme Einbindung von Google Maps

Benutzerverwaltung mit Jetstream & Teams

Support & Weiterentwicklung

Feature-Wünsche und Fehlerberichte können über GitHub Issues eingereicht werden. Updates werden regelmäßig implementiert, insbesondere Sicherheits- und Performance-Optimierungen.

© 2025 MiniFinds GbR | Entwickelt von LMZ Media