# Current state

## Confirmed

- LMZ Dev workspace initialized.
- Admin-API-Testoberflaeche unter `resources/views/livewire/admin/tests/api-tests.blade.php` unterstuetzt jetzt den UVS-Dokumenttest fuer Angebot und Vertrag.
- `ApiUvsService::testSignedDocument()` erzeugt die signierte URL, ruft sie ohne API-Key ab und validiert HTTP-Status, PDF-Content-Type und `%PDF-`-Dateikopf.
- Signierte URLs werden vor dem serverseitigen Abruf auf denselben Ursprung wie die konfigurierte UVS-API begrenzt.

## Verification

- `php -l app/Services/ApiUvs/ApiUvsService.php`: passed.
- `php -l app/Livewire/Admin/Tests/ApiTests.php`: passed.
- Inline HTTP-Fake-Smoke-Test: sign POST mit API-Key und PDF GET ohne API-Key, passed.
- Fokussierte Blade-Kompilierung der geaenderten View: passed.
- `php artisan test --testsuite=Unit --stop-on-failure`: 27 passed, 149 assertions.
- `git diff --check`: passed.

## Risks and blockers

- Globales `php artisan view:cache` bleibt durch eine bereits vorhandene, nicht zu dieser Aenderung gehoerende fehlende Blade-Komponente `admin-layout` blockiert; Cache wurde danach geleert.
- Der echte Remote-Test benoetigt gueltige Admin-Settings fuer UVS-API-URL/API-Key, die API-Key-Ability `documents.sign` sowie lesbaren Zugriff der UVS-API auf die gewaehlte PDF.
