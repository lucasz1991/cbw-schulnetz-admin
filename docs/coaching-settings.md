# Einzelcoaching-Einstellung

Unter **Konfiguration → Einzelcoaching** kann ausschließlich die Superadmin-Rolle
`admin` (dieselbe Rolle wie beim API-Tab) die Funktion aktivieren oder deaktivieren.
Die Berechtigung wird beim Anzeigen und bei jedem Speichern serverseitig geprüft.

Gespeichert wird ein Boolean in der vorhandenen gemeinsamen Settings-Tabelle
unter `coaching.enabled`. Base und Admin lesen diesen Wert ohne Settings-Cache.
Kein ENV-Schalter und keine neue Migration sind erforderlich. Ohne gespeicherten
Wert ist die Funktion aus. Nach dem Update muss eine bisherige ENV-Freigabe
deshalb einmal im Adminbereich neu gesetzt werden.

Base und Admin müssen gemeinsam aktualisiert werden und dieselbe
Schulnetz-Datenbank verwenden. Die vorhandenen Coaching-Migrationen gehören
weiterhin ausschließlich in Base. UVS-Abgleich, API-Verbindung und die
Vertragsfreigabe im UVS bleiben Voraussetzung.

Deaktivieren stoppt neue Abgleichstarts und sperrt die Planung. Daten bleiben
erhalten; bereits laufende Abgleiche werden nicht abgebrochen. Details stehen
im Base-Repository in `docs/coaching-settings.md`.


## Erweiterung vom 22.09.2026
Für Systemmitteilungen und E-Mails ist zusätzlich die neue Base-Migration `2026_09_22_100000_create_coaching_notices.php` erforderlich. Die Settings-Tabelle selbst benötigt weiterhin keine Migration. Einrichtung und Ereignisse: `docs/coaching-notifications.md`.
