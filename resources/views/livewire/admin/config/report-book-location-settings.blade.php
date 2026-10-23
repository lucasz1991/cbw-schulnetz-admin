@php
    $current = $state['current'] ?? [];
    $planned = $state['scheduled'] ?? null;
    $locationName = collect($institutions)->firstWhere('value', $instituteId)['label'] ?? $instituteId;
    $counts = $impact['counts'] ?? $impact ?? [];
    $releaseRequested = $stampEnabled && (!($current['stamp_enabled'] ?? false) || (($current['required'] ?? true) && !$required));
    $runLabels = ['pending' => 'Vorgemerkt', 'running' => 'In Bearbeitung', 'completed' => 'Abgeschlossen', 'failed' => 'Prüfung erforderlich', 'cancelled' => 'Storniert', 'superseded' => 'Vorzeitig beendet'];
@endphp
<section class="reportbook-location-settings" aria-labelledby="reportbook-location-title">
    <header class="rb-header">
        <p class="rb-eyebrow">Teilnehmer · Berichtshefte</p>
        <h2 id="reportbook-location-title">Berichtshefte je Standort</h2>
        <p class="rb-help">Berichtspflicht festlegen, bestehende Einträge behandeln und Änderungen veröffentlichen.</p>
    </header>

    @if(session()->has('reportbook_policy_saved'))
        <p role="status" class="rb-alert rb-alert-success">{{ session('reportbook_policy_saved') }}</p>
    @endif
    @error('policy') <p role="alert" class="rb-alert rb-alert-error">{{ $message }}</p> @enderror
    @error('publication_date') <p role="alert" class="rb-alert rb-alert-error">{{ $message }}</p> @enderror
    @error('stamp_path') <p role="alert" class="rb-alert rb-alert-error">{{ $message }}</p> @enderror
    @if(!$schemaReady)
        <p role="status" class="rb-alert rb-alert-warning">Die Standortsteuerung ist noch nicht eingerichtet. Speichern ist derzeit nicht möglich.</p>
    @endif

    <div class="rb-location-bar">
        <div class="rb-location-field">
            <label for="reportbook-institute" class="rb-label">Standort</label>
            <select id="reportbook-institute" wire:model.live="instituteId" class="rb-control" wire:loading.attr="disabled" aria-describedby="reportbook-institute-help">
                <option value="">Standort auswählen</option>
                @foreach($institutions as $institution)
                    <option value="{{ $institution['value'] }}">{{ $institution['label'] }}</option>
                @endforeach
            </select>
            <p id="reportbook-institute-help" class="rb-footnote">Die Einstellungen gelten ausschließlich für diesen Standort.</p>
            @error('instituteId') <p role="alert" class="rb-error">{{ $message }}</p> @enderror
        </div>
        @if($instituteId !== '' && $schemaReady)
            <x-secondary-button type="button" wire:click="loadState" wire:loading.attr="disabled" class="rb-button rb-button-quiet">Gespeicherten Stand laden</x-secondary-button>
        @endif
    </div>

    @if($instituteId !== '' && $schemaReady)
        <div class="rb-saved-state" aria-label="Gespeicherte Standorteinstellungen">
            <div class="rb-saved-item">
                <p class="rb-state-label"><span class="rb-status-dot" aria-hidden="true"></span>Aktuell gültig · {{ $locationName }}</p>
                <h3>{{ ($current['required'] ?? true) ? 'Berichtshefte erforderlich' : 'Keine Berichtsheftpflicht' }}</h3>
                <p class="rb-help">{{ ($current['required'] ?? true) ? 'Teilnehmer können Berichtshefte führen und einreichen.' : 'Freigegebene Einträge bleiben im Archiv verfügbar.' }}</p>
                @if($current['stamp_enabled'] ?? false)
                    <p class="rb-footnote">Standortstempel aktiviert</p>
                @endif
            </div>
            <div class="rb-saved-item rb-planned-state">
                @if($planned)
                    <p class="rb-state-label">Geplant ab {{ \Illuminate\Support\Carbon::parse($planned['effective_at'])->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr</p>
                    <h3>{{ ($planned['required'] ?? true) ? 'Berichtshefte erforderlich' : 'Keine Berichtsheftpflicht' }}</h3>
                    <p class="rb-help">Standortstempel {{ ($planned['stamp_enabled'] ?? false) ? 'aktiviert' : 'deaktiviert' }}</p>
                    <button type="button" class="rb-text-button" wire:click="cancel" wire:confirm="Die noch nicht wirksame Änderung stornieren?" wire:loading.attr="disabled">Planung stornieren</button>
                @else
                    <p class="rb-state-label">Nächste Änderung</p>
                    <h3>Keine Veröffentlichung geplant</h3>
                    <p class="rb-help">Der aktuelle Stand gilt bis zur nächsten Veröffentlichung.</p>
                @endif
            </div>
        </div>

        <div class="rb-workspace">
            <form id="reportbook-settings-form" wire:submit="preview" class="rb-editor">
                <div class="rb-section-heading">
                    <h3>Einstellungen bearbeiten</h3>
                    <p class="rb-help">{{ $planned ? 'Das Formular enthält die geplante Änderung.' : 'Prüfe deine Änderung in der Vorschau, bevor du sie speicherst.' }}</p>
                </div>
                <fieldset wire:loading.attr="disabled">
                    <legend class="sr-only">Standortregel bearbeiten</legend>
                    <div class="rb-setting">
                        <span class="rb-step" aria-hidden="true">01</span>
                        <div class="rb-setting-content">
                            <label for="reportbook-required" class="rb-setting-title">Berichtsheftpflicht</label>
                            <select id="reportbook-required" wire:model.live.boolean="required" class="rb-control" aria-describedby="reportbook-required-help">
                                <option value="true">Ja – erforderlich</option>
                                <option value="false">Nein – nicht erforderlich</option>
                            </select>
                            <p id="reportbook-required-help" class="rb-help">
                                @if($required)
                                    Teilnehmer führen und reichen ihre Berichtshefte wie gewohnt ein.
                                @else
                                    Freigegebene Einträge bleiben lesbar und herunterladbar. Offene Inhalte werden ausgeblendet und bleiben für eine spätere Reaktivierung gespeichert.
                                @endif
                            </p>
                            @error('required') <p role="alert" class="rb-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="rb-setting">
                        <span class="rb-step" aria-hidden="true">02</span>
                        <div class="rb-setting-content">
                            <div class="rb-title-row"><h4 class="rb-setting-title">Bestehende Einreichungen</h4><span class="rb-tag">Optional</span></div>
                            <label for="reportbook-stamp-enabled" class="rb-checkbox-label">
                                <input id="reportbook-stamp-enabled" type="checkbox" wire:model.live="stampEnabled" aria-describedby="reportbook-stamp-help">
                                <span>Mit Standortstempel freigeben</span>
                            </label>
                            <p id="reportbook-stamp-help" class="rb-help">Einmalige Freigabe nur für eingereichte Einträge mit wartender Prüfaufgabe. Bestehende Unterschriften und Freigaben bleiben erhalten.</p>
                            @error('stampEnabled') <p role="alert" class="rb-error">{{ $message }}</p> @enderror
                            @if($stampEnabled)
                                <div class="rb-upload">
                                    <div class="rb-title-row">
                                        <label for="reportbook-stamp" class="rb-label">Stempelbild</label>
                                        <span class="rb-tag {{ !empty($stamp['stamp_path']) ? 'rb-tag-success' : '' }}">{{ !empty($stamp['stamp_path']) ? 'Hinterlegt' : 'Erforderlich' }}</span>
                                    </div>
                                    <p id="reportbook-upload-help" class="rb-footnote">Originalstempel einschließlich Hinweis · PNG/JPEG · höchstens 4 MiB</p>
                                    <input id="reportbook-stamp" type="file" wire:model="stampUpload" accept="image/png,image/jpeg" class="rb-file" aria-describedby="reportbook-upload-help">
                                    <x-secondary-button type="button" wire:click="uploadStamp" wire:loading.attr="disabled" wire:target="stampUpload,uploadStamp" class="rb-button">Stempel hochladen</x-secondary-button>
                                    <p wire:loading wire:target="stampUpload,uploadStamp" role="status" class="rb-footnote">Stempel wird verarbeitet …</p>
                                    @error('stampUpload') <p role="alert" class="rb-error">{{ $message }}</p> @enderror
                                    @if($stampPreview)
                                        <figure class="rb-stamp-preview"><img src="{{ $stampPreview }}" alt="Vorschau des hinterlegten CBW-Stempels"><figcaption class="rb-footnote">Hinterlegte Stempelversion</figcaption></figure>
                                    @elseif(!empty($stamp['stamp_path']))
                                        <p class="rb-error">Eine Stempelversion ist hinterlegt. Ihre Vorschau ist zurzeit nicht erreichbar; vor dem Speichern wird die Datei erneut geprüft.</p>
                                    @endif
                                </div>
                                <p class="rb-footnote">Ein Bildwechsel oder die Reaktivierung der Berichtspflicht löst keine neue Bestandsfreigabe aus.</p>
                            @elseif(!$required)
                                <p class="rb-footnote">Offene Prüfaufgaben werden ausgeblendet und bleiben gespeichert.</p>
                            @endif
                        </div>
                    </div>

                    <div class="rb-setting">
                        <span class="rb-step" aria-hidden="true">03</span>
                        <div class="rb-setting-content">
                            <label for="reportbook-publication" class="rb-setting-title">Veröffentlichungsdatum</label>
                            <input id="reportbook-publication" type="date" wire:model.live="publicationDate" min="{{ now('Europe/Berlin')->toDateString() }}" class="rb-control rb-date" aria-describedby="reportbook-date-help" required>
                            <p id="reportbook-date-help" class="rb-help">Heute: sofort nach dem Speichern.<br>Zukünftiges Datum: ab 00:00 Uhr, Zeitzone Berlin.</p>
                            @error('publicationDate') <p role="alert" class="rb-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>
            </form>

            <aside class="rb-preview" aria-labelledby="reportbook-preview-title">
                <div>
                    <p class="rb-eyebrow">Vor dem Speichern</p>
                    <h3 id="reportbook-preview-title">{{ $impact !== null ? 'Vorläufige Vorschau' : 'Änderung prüfen' }}</h3>
                </div>
                @if($stampEnabled && ($errors->has('stampUpload') || $errors->has('stamp_path')))
                    <p role="alert" class="rb-error">Bitte prüfe das Stempelbild, bevor du fortfährst. <a href="#reportbook-stamp" class="rb-error-link">Zum Stempelbild</a></p>
                @endif
                @if($impact !== null)
                    <div class="rb-preview-content" aria-live="polite">
                        <dl class="rb-summary">
                            <div><dt>Standort</dt><dd>{{ $locationName }}</dd></div>
                            <div><dt>Berichtspflicht</dt><dd>{{ $required ? 'Ja' : 'Nein · Archivzugriff' }}</dd></div>
                            <div><dt>Veröffentlichung</dt><dd>{{ \Illuminate\Support\Carbon::parse($publicationDate, 'Europe/Berlin')->format('d.m.Y') }}<small>{{ $publicationDate === now('Europe/Berlin')->toDateString() ? 'Sofort nach dem Speichern' : '00:00 Uhr · Berlin' }}</small></dd></div>
                            <div><dt>Standortstempel</dt><dd>{{ $stampEnabled ? 'Aktiviert' : 'Deaktiviert' }}</dd></div>
                        </dl>
                        <div class="rb-inventory">
                            <h4>Aktueller Bestand</h4>
                            <dl>
                                @foreach(['participants' => 'Teilnehmer', 'books' => 'Berichtshefte', 'submitted_entries' => 'Eingereichte Einträge', 'tasks' => 'Offene Prüfaufgaben'] as $key => $label)
                                    @if(isset($counts[$key]))
                                        <div><dt>{{ $label }}</dt><dd>{{ number_format($counts[$key], 0, ',', '.') }}</dd></div>
                                    @endif
                                @endforeach
                            </dl>
                        </div>
                        @if($releaseRequested)
                            <div class="rb-release">
                                <span class="rb-release-count">{{ number_format($counts['eligible_entries'] ?? 0, 0, ',', '.') }}</span>
                                <div><strong>Einträge für die Stempelfreigabe</strong><p class="rb-footnote">{{ $counts['eligible_books'] ?? 0 }} Berichtshefte · {{ $counts['eligible_tasks'] ?? 0 }} Prüfaufgaben</p></div>
                            </div>
                        @elseif($stampEnabled)
                            <p class="rb-help">Diese Änderung löst keine neue Bestandsfreigabe aus.</p>
                        @endif
                        @if($releaseRequested && !empty($impact['excluded']))
                            <details class="rb-exclusions">
                                <summary>{{ count($impact['excluded']) }} Berichtshefte von der Stempelfreigabe ausgeschlossen</summary>
                                <ul>
                                    @foreach($impact['excluded'] as $excluded)
                                        <li>Berichtsheft #{{ $excluded['book_id'] }}: {{ $excluded['reason'] }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                        <p class="rb-footnote">Der verbindliche Bestand wird zum Wirksamwerden einmalig ermittelt. Bis dahin können sich diese Zahlen ändern.</p>
                        <x-button type="button" wire:click="schedule" wire:confirm="Diese Standortregel zum gewählten Datum verbindlich veröffentlichen?" wire:loading.attr="disabled" class="rb-button rb-button-primary">Änderung verbindlich speichern</x-button>
                    </div>
                @else
                    <p class="rb-help">Die Vorschau zeigt den gewählten Termin und die betroffenen Teilnehmer, Berichtshefte und Prüfaufgaben.</p>
                    <p class="rb-preservation-note">Bereits freigegebene Einträge und persönliche Unterschriften bleiben erhalten.</p>
                @endif
                <x-secondary-button type="submit" form="reportbook-settings-form" wire:loading.attr="disabled" class="rb-button {{ $impact === null ? 'rb-button-primary' : 'rb-button-quiet' }}">{{ $impact === null ? 'Vorschau prüfen' : 'Vorschau aktualisieren' }}</x-secondary-button>
                <p wire:loading wire:target="preview,schedule" role="status" class="rb-footnote">Änderung wird geprüft …</p>
            </aside>
        </div>

        @if(!empty($state['runs']))
            <section class="rb-runs" aria-labelledby="reportbook-runs-title">
                <div class="rb-section-heading"><h3 id="reportbook-runs-title">Bestandsfreigaben</h3><p class="rb-help">Fortschritt und Protokolle der einmaligen Stempelfreigaben.</p></div>
                @foreach($state['runs'] as $run)
                    @php
                        $bookResults = collect($run['results']['books'] ?? []);
                        $hasResults = isset($run['results']['approved_entries']) || $bookResults->isNotEmpty();
                        $approvedEntries = $run['results']['approved_entries'] ?? $bookResults->sum('approved_entries');
                        $completedTasks = $run['results']['completed_tasks'] ?? $bookResults->sum('completed_tasks');
                        $skippedBooks = $run['results']['skipped_books'] ?? $bookResults->where('status', 'skipped')->count();
                    @endphp
                    <details class="rb-run" wire:key="reportbook-run-{{ $run['id'] }}" @if(in_array($run['status'] ?? 'pending', ['pending', 'running', 'failed'])) open @endif>
                        <summary><span>Freigabe #{{ $run['id'] }}</span><span class="rb-tag {{ ($run['status'] ?? '') === 'completed' ? 'rb-tag-success' : 'rb-tag-warning' }}">{{ $runLabels[$run['status'] ?? 'pending'] ?? $run['status'] }}</span></summary>
                        <div class="rb-run-body">
                            @if($hasResults)
                                <dl class="rb-outcomes">
                                    <div><dt>Freigegebene Einträge</dt><dd>{{ $approvedEntries }}</dd></div>
                                    <div><dt>Erledigte Prüfaufgaben</dt><dd>{{ $completedTasks }}</dd></div>
                                    <div><dt>Übersprungene Berichtshefte</dt><dd>{{ $skippedBooks }}</dd></div>
                                </dl>
                                @if(($run['status'] ?? '') !== 'completed')
                                    <p class="rb-footnote">Bisher gespeicherte Ergebnisse. Bereits erteilte Freigaben bleiben bestehen.</p>
                                @endif
                            @elseif(($run['status'] ?? 'pending') === 'pending')
                                <p class="rb-help">Die Verarbeitung ist vorgemerkt. Es wurden noch keine Einträge freigegeben.</p>
                            @else
                                <p class="rb-help">Für diesen Lauf sind noch keine ausgeführten Freigaben gespeichert.</p>
                            @endif
                            @if(!empty($run['error']))
                                <p role="status" class="rb-alert rb-alert-warning">{{ $run['error'] }}</p>
                            @endif
                            <x-secondary-button type="button" wire:click="downloadAudit({{ $run['id'] }})" wire:loading.attr="disabled" class="rb-button">Vollständiges Protokoll herunterladen</x-secondary-button>
                        </div>
                    </details>
                @endforeach
            </section>
        @endif

        <details class="rb-history">
            <summary><span>Änderungsverlauf</span><span class="rb-tag">{{ count($state['revisions'] ?? []) }} {{ count($state['revisions'] ?? []) === 1 ? 'Revision' : 'Revisionen' }}</span></summary>
            <div class="rb-history-body">
                @forelse($state['revisions'] ?? [] as $revision)
                    <div class="rb-history-row" wire:key="reportbook-revision-{{ $revision['revision_id'] }}">
                        <div><strong>{{ \Illuminate\Support\Carbon::parse($revision['effective_at'])->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr</strong><p class="rb-help">Berichtspflicht {{ ($revision['required'] ?? true) ? 'Ja' : 'Nein' }} · Standortstempel {{ ($revision['stamp_enabled'] ?? false) ? 'aktiviert' : 'deaktiviert' }}</p></div>
                        <span class="rb-footnote">Revision #{{ $revision['revision_id'] }}{{ !empty($revision['cancelled']) ? ' · storniert' : '' }}</span>
                    </div>
                @empty
                    <p class="rb-help">Noch keine Änderungen gespeichert. Es gilt der Standard: Berichtshefte erforderlich.</p>
                @endforelse
            </div>
        </details>
    @elseif($schemaReady)
        <div class="rb-empty"><h3>Standort auswählen</h3><p class="rb-help">Danach kannst du die aktuelle Berichtspflicht und geplante Änderungen einsehen.<br>Ohne eigene Regel sind Berichtshefte weiterhin erforderlich.</p></div>
    @endif
    <p wire:loading wire:target="instituteId,loadState,cancel" role="status" class="rb-footnote">Gespeicherter Stand wird geladen …</p>
</section>
