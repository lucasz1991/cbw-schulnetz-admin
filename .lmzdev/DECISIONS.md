# Decisions

Record durable decisions with date, context, decision, and consequences.

## 2026-08-05 | Dokumenttest prueft Signatur und Dateiinhalt

- Context: `POST /api/documents/sign` allein beweist nicht, dass IIS/PHP die PDF aus dem UVS-Verzeichnis lesen und ausliefern kann.
- Decision: Der Admin-Test ruft die signierte URL direkt danach ohne API-Key ab und verlangt erfolgreichen Status, `application/pdf` und einen `%PDF-`-Dateikopf.
- Consequence: Pfad-, Rechte-, Signatur-, HTTPS- und Auslieferungsfehler werden im selben Test sichtbar; der erzeugte Link kann zusaetzlich im Browser geoeffnet werden.

## 2026-08-05 | Serverabruf nur fuer konfigurierten UVS-Ursprung

- Context: Eine von einem externen Dienst gelieferte URL darf nicht ungeprueft serverseitig abgerufen werden.
- Decision: Schema, Host und effektiver Port der signierten URL muessen mit `api.uvs_api_url` uebereinstimmen.
- Consequence: APP_URL-/HTTPS-Abweichungen werden als Testfehler sichtbar und fremde Ziele werden nicht abgerufen.

## 2026-08-05 | Dokumenttest aus dem CBW-Admin entfernen

- Context: Der Test soll unter Einstellungen -> Basis direkt in der UVS-API und damit unter der echten API-/IIS-Identitaet laufen.
- Decision: Die zuvor ergaenzten Dokumentfelder, Testlistenoption, HTTP-Fakes, PDF-Link und Service-Pruefmethode werden vollstaendig aus dem CBW-Admin entfernt.
- Consequence: Keine doppelte Testoberflaeche und keine API-Key-Abhaengigkeit im CBW-Admin; die Dateirechte werden an der richtigen Installation geprueft.

## 2026-08-23 | Berichtsheft-Uebernahme ueber Berechtigung und erwarteten Zustand steuern

- Context: Vertretungen muessen aktive Berichtsheft-Aufgaben abwesender Mitarbeiter uebernehmen koennen, ohne doppelte Bearbeitung oder veraltete Livewire-Aktionen.
- Decision: Die bestehende Ability `jobs.view` bleibt der Zugangsschalter. Zuweisung, Uebernahme, Freigabe und Abschluss laufen ueber zentrale Actions mit Transaktion, Zeilensperre und Expected-Assignee-Pruefung; Namen werden nicht im Code hinterlegt. Vor einer moeglichen Task-Erzeugung sperren Base und Admin denselben ReportBook-Parent.
- Consequence: Der bestehende Standardfall fuer freie Aufgaben bleibt erhalten, waehrend eine bestaetigte Uebernahme denselben Task atomar auf den neuen Bearbeiter umstellt und parallele Erzeuger keine doppelten Tasks anlegen.

## 2026-08-23 | Externe Pruefungsantraege verwenden in Base und Admin denselben Typvertrag

- Context: Base speichert neue Antraege als `external_makeup`; die bestehende Admin-Detailansicht pruefte nur den historischen Namen `external_exam`.
- Decision: Admin unterstuetzt beide Typnamen lesend und verwendet dieselben rueckwaertskompatiblen Accessors wie Base.
- Consequence: Neue und historische Antraege zeigen Klasse, Institution, Pruefungsname, Termin, eingefrorenen Preis und einen lesbaren Grund statt eines technischen Schluessels.
