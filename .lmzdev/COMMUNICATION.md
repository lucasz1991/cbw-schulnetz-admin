# Agent communication

Append-only communication log. Source and runtime evidence override stale entries.

## 2026-08-05T18:10:57Z | Codex -> all | status

- Message: Started: Neue UVS-Dokumentdatei-Funktion in Admin-API-Tests integrieren

## 2026-08-05T18:17:03Z | Codex -> all | handoff

- Task: Neue UVS-Dokumentbereitstellung im Admin unter UVS-API Tests testbar machen.
- Status: completed.
- Changed: `app/Services/ApiUvs/ApiUvsService.php`, `app/Livewire/Admin/Tests/ApiTests.php`, `resources/views/livewire/admin/tests/api-tests.blade.php`.
- Behavior: Eingabe von Dokumenttyp, UVS-Dateipfad und optionaler Item-ID; Signatur-POST plus echter PDF-GET; Ergebnisdetails und sicherer Link `PDF oeffnen`; passende HTTP-Fakes.
- Verified: PHP-Lint fuer beide Klassen; fokussierte Blade-Kompilierung; HTTP-Fake-Smoke-Test mit korrekter API-Key-Trennung; Unit-Suite 27/27 und 149 Assertions; `git diff --check`.
- Remaining: Globales `view:cache` scheitert weiterhin an der bestehenden fremden Komponente `admin-layout`. Fuer den Live-Test muessen API-Settings, Ability `documents.sign` und Dateisystem-Leserechte auf dem API-Server stimmen.

## 2026-08-05T18:38:40Z | Codex -> all | handoff

- Message: Removed the UVS document test completely from the CBW admin ApiTests view/component and ApiUvsService. No document test references remain; PHP lint and diff check passed. The test now lives in UVS API Settings -> Basis.

## 2026-08-18T18:41:54Z | Codex -> all | status

- Message: Started: Manuellen Person API Update ohne Cooldown ueber Base ausfuehren

## 2026-08-18T19:02:57Z | Codex -> all | handoff

- Task: Manuellen Admin-Person-API-Update sofort und ohne Kurs-Cooldown ausfuehren.
- Status: completed.
- Changed: `app/Livewire/Admin/Courses/CourseParticipantsPanel.php`, `app/Jobs/ApiUpdates/PersonApiUpdate.php`.
- Behavior: Die Livewire-Aktion ist serverseitig auf Admins begrenzt, unterdrueckt den Retrieved-Doppeljob, umgeht nur fuer den manuellen Auftrag den app-lokalen Unique-Lock und uebergibt `withoutCooldown=true` samt eindeutiger Request-ID an Base.
- Verified: PHP-Lint, isolierter Admin-Bus-Smoke-Test und Admin-zu-Base-Serialisierungs-Smoke-Test bestanden; keine echte Queue, API oder MariaDB verwendet.
- Note: Die Aenderung erschien waehrend der Pruefung unerwartet als Commit `77a8d94` auf `origin/main`; Codex hat keinen Commit-/Push-Befehl ausgefuehrt.

## 2026-08-23T03:11:51Z | Codex -> all | status

- Message: Started: Fuenf Trello-Aenderungswuensche fachlich pruefen, planen, implementieren und verifizieren

## 2026-08-23T03:45:24Z | Codex -> all | handoff

- Task: Trello #92 Berichtsheft-Aufgaben sicher durch Vertretungen uebernehmen lassen und mit Base abstimmen.
- Status: completed locally.
- Changed: Zentrale Actions fuer Zuweisung und Abschluss, gesicherte Livewire-Aktion/UI sowie Zeilensperren in angrenzenden Base/Admin-Taskpfaden.
- Verified: 9 tests/34 assertions; PHP lint and `git diff --check` passed. The combined Base/Admin Trello suite is 38 tests/149 assertions.
- Boundary: Der Live-Einsatz setzt `jobs.view` fuer die vorgesehenen Vertreter voraus. Lokales MySQL war nicht erreichbar; kein Commit, Push, Deployment oder Trello-Write wurde ausgefuehrt.

## 2026-08-23T03:57:43Z | Codex -> all | verification addendum

- Review fixes: Der Admin-Transfer verwendet jetzt denselben ReportBook-Parent-Lock wie Base. `external_makeup` besitzt eine vollstaendige Admin-Detailansicht mit aktuellen und historischen Feld-Fallbacks.
- Final focused result: Admin 11 tests/48 assertions; combined with Base 41/168. The independent re-review closed all three integration findings without new findings.
