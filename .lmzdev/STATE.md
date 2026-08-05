# Current state

## Confirmed

- LMZ Dev workspace initialized.
- Der zuvor ergaenzte UVS-Dokumenttest wurde aus der CBW-Admin-API-Testoberflaeche, der Livewire-Komponente und `ApiUvsService` wieder vollstaendig entfernt. Die Pruefung liegt stattdessen in der UVS-API unter Einstellungen -> Basis.

## Verification

- `php -l app/Services/ApiUvs/ApiUvsService.php`: passed.
- `php -l app/Livewire/Admin/Tests/ApiTests.php`: passed.
- Inline HTTP-Fake-Smoke-Test: sign POST mit API-Key und PDF GET ohne API-Key, passed.
- Fokussierte Blade-Kompilierung der geaenderten View: passed.
- `php artisan test --testsuite=Unit --stop-on-failure`: 27 passed, 149 assertions.
- `git diff --check`: passed.
- Nach Entfernung: PHP-Lint fuer `ApiTests.php` und `ApiUvsService.php`; keine Referenz auf `document_signed_pdf`, `testSignedDocument` oder die Dokumenttest-UI; `git diff --check` passed.

## Risks and blockers

- Globales `php artisan view:cache` bleibt durch eine bereits vorhandene, nicht zu dieser Aenderung gehoerende fehlende Blade-Komponente `admin-layout` blockiert; Cache wurde danach geleert.
- Kein UVS-Dokumenttest verbleibt im CBW-Admin; der echte Servercheck erfolgt in der UVS-API selbst.
