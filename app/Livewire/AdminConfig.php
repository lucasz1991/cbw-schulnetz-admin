<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Setting;

class AdminConfig extends Component
{
    // ------------------------------------------------------------------
    // Basis / E-Mail
    // ------------------------------------------------------------------

    /** @var string|null */
    public $adminEmail;

    // ------------------------------------------------------------------
    // Admin-Notifications: META (statisch, NICHT überschreiben)
    // ------------------------------------------------------------------
    public $adminEmailNotificationMeta = [
        'new_user_request' => [
            'label' => 'Neuer Antrag eines Teilnehmers angelegt ( Admin Mail Adresse )',
            'description' => 'Benachrichtigt Sie, sobald ein Teilnehmer einen neuen Antrag eingereicht hat – z. B. Fehlzeitmeldung, Nachklausur-Anmeldung oder Anmeldung zur externen Prüfung. Mails an Admin Mail Adresse.',
            'default' => false,
        ],
        'new_user_request_to_inst' => [
            'label' => 'Neuer Antrag eines Teilnehmers angelegt ( Institut Adressen )',
            'description' => 'Benachrichtigt Sie, sobald ein Teilnehmer einen neuen Antrag eingereicht hat – z. B. Fehlzeitmeldung, Nachklausur-Anmeldung oder Anmeldung zur externen Prüfung. Diese Mails werden an das Jeweilige Institut gesendet. Mail Adressen werden aus Uvs geladen.',
            'default' => false,
        ],
    ];

    // Admin-Notifications: Werte (nur bools; werden geladen/gespeichert)
    public $adminEmailNotifications = [
        'new_user_request' => false,
        'new_user_request_to_inst' => false,
    ];

    // Admin-Notifications: Vorschlaege (sichtbar, aber nicht aktivierbar)
    public $adminEmailNotificationSuggestions = [
        'course_start_without_lecturer' => [
            'label' => 'Kursstart ohne Dozentenzuordnung',
            'description' => 'Automatische Nachricht, wenn ein Kurs in den naechsten 7 Tagen startet, aber kein Dozent zugeordnet ist.',
        ],
        'final_exam_missing' => [
            'label' => 'Abschlusspruefung nicht geplant',
            'description' => 'Automatische Nachricht, wenn ein Kursende naht und noch kein Pruefungstermin hinterlegt wurde.',
        ],
        'attendance_entries_incomplete' => [
            'label' => 'Anwesenheiten unvollstaendig',
            'description' => 'Automatische Nachricht, wenn in laufenden Kursen fuer mehrere Tage keine Anwesenheit erfasst wurde.',
        ],
        'instructor_invoice_overdue' => [
            'label' => 'Dozentenrechnung ueberfaellig',
            'description' => 'Automatische Nachricht, wenn ein Baustein beendet ist, aber die Dozentenrechnung nach 14 Tagen noch fehlt.',
        ],
        'api_sync_failed' => [
            'label' => 'UVS-Synchronisation fehlgeschlagen',
            'description' => 'Automatische Nachricht, wenn die Synchronisation von Teilnehmer- oder Kursdaten mit UVS fehlschlaegt.',
        ],
    ];

    // ------------------------------------------------------------------
    // User-Notifications: META (statisch, NICHT überschreiben)
    // ------------------------------------------------------------------
    public $userEmailNotificationMeta = [
        'reminder_missing_report_book' => [
            'label' => 'Berichtsheft fehlt',
            'description' => 'Erinnerung, wenn ein Berichtsheft nicht vollständig ausgefüllt wurde. 2 Wochen nach Kursende, wenn noch fehlende Einträge vorhanden sind.',
            'default' => false,
        ],
    ];


    // User-Notifications: Vorschlaege (sichtbar, aber nicht aktivierbar)
    public $userEmailNotificationSuggestions = [
        'lecturer_module_documentation_missing' => [
            'label' => 'Dozenten Baustein-Dokumentation fehlt',
            'description' => 'Automatische Nachricht, wenn fuer einen Baustein die Dozenten-Dokumentation fehlt.',
        ],
        'rote_faden_missing' => [
            'label' => 'Dozenten Roter Faden fehlt',
            'description' => 'Automatische Nachricht, wenn der rote Faden fuer einen Kurs fehlt.',
        ],
        'attendance_proof_missing' => [
            'label' => 'Anwesenheitsnachweis fehlt',
            'description' => 'Automatische Nachricht, wenn fuer einen Baustein kein Anwesenheitsnachweis vorliegt.',
        ],
        'exam_results_missing_by_lecturer' => [
            'label' => 'Pruefungsergebnisse fehlen vom Dozenten',
            'description' => 'Automatische Nachricht, wenn nach einer Pruefung noch keine Ergebnisse durch den Dozenten eingetragen wurden.',
        ],
        'lecturer_module_invoice_missing' => [
            'label' => 'Dozenten Baustein Rechnung fehlt',
            'description' => 'Automatische Nachricht, wenn fuer einen abgeschlossenen Baustein noch keine Dozenten-Rechnung hinterlegt wurde.',
        ],
        'participant_education_material_confirmation_missing' => [
            'label' => 'Bildungsmittel-Bestaetigung fehlt (Teilnehmer)',
            'description' => 'Automatische Erinnerung an Teilnehmer, wenn die Bestaetigung zu den Bildungsmitteln noch aussteht.',
        ],
    ];

    // User-Notifications: Werte (nur bools; werden geladen/gespeichert)
    public $userEmailNotifications = [
        'reminder_missing_report_book' => false,
    ];

    // ------------------------------------------------------------------
    // API Settings
    // ------------------------------------------------------------------
    public $apiSettings = [
        'base_api_url' => '',
        'base_api_key' => '',
        'uvs_api_url'  => '',
        'uvs_api_key'  => '',
    ];

    // ------------------------------------------------------------------
    // Lifecycle
    // ------------------------------------------------------------------

    public function mount(): void
    {
        $this->loadSettings();
    }

    // ------------------------------------------------------------------
    // Loading
    // ------------------------------------------------------------------

    public function loadSettings(): void
    {
        // ---- Mails allgemein ----
        $mailSettings = Setting::where('type', 'mails')->get();

        foreach ($mailSettings as $setting) {
            $key = $setting->key;

            // Admin E-Mail
            if ($key === 'admin_email') {
                $this->adminEmail = $setting->value;
                continue;
            }

            // Admin-Flags (bool) – nur Werte-Array anfassen
            if (array_key_exists($key, $this->adminEmailNotifications)) {
                $this->adminEmailNotifications[$key] = $this->decodeToBool($setting->value, $this->getAdminDefault($key));
                continue;
            }

            // User-Flags (bool) – nur Werte-Array anfassen
            if (array_key_exists($key, $this->userEmailNotifications)) {
                $this->userEmailNotifications[$key] = $this->decodeToBool($setting->value, $this->getUserDefault($key));
                continue;
            }
        }

        // ---- API Settings ----
        $this->apiSettings['base_api_url'] = Setting::where('key', 'base_api_url')->value('value') ?? '';
        $this->apiSettings['base_api_key'] = Setting::where('key', 'base_api_key')->value('value') ?? '';
        $this->apiSettings['uvs_api_url']  = Setting::where('key', 'uvs_api_url')->value('value') ?? '';
        $this->apiSettings['uvs_api_key']  = Setting::where('key', 'uvs_api_key')->value('value') ?? '';
    }

    // ------------------------------------------------------------------
    // Saving
    // ------------------------------------------------------------------

    public function saveApiSettings(): void
    {
        Setting::updateOrCreate(['key' => 'base_api_url', 'type' => 'api'], ['value' => $this->apiSettings['base_api_url']]);
        Setting::updateOrCreate(['key' => 'base_api_key', 'type' => 'api'], ['value' => $this->apiSettings['base_api_key']]);
        Setting::updateOrCreate(['key' => 'uvs_api_url',  'type' => 'api'], ['value' => $this->apiSettings['uvs_api_url']]);
        Setting::updateOrCreate(['key' => 'uvs_api_key',  'type' => 'api'], ['value' => $this->apiSettings['uvs_api_key']]);

        $this->dispatch('showAlert', 'API-Einstellungen wurden erfolgreich gespeichert.', 'success');
    }

    public function saveAdminMailSettings(): void
    {
        $this->persistAdminMailSettings();
        $this->dispatch('showAlert', 'Admin E-Mail Einstellungen wurden gespeichert.', 'success');
    }

    public function saveUserMailSettings(): void
    {
        $this->persistUserMailSettings();
        $this->dispatch('showAlert', 'Benutzer E-Mail Einstellungen wurden gespeichert.', 'success');
    }

    public function updatedAdminEmailNotifications($value, $key): void
    {
        if (! array_key_exists($key, $this->adminEmailNotifications)) {
            return;
        }

        $this->persistAdminMailSettings();
    }

    public function updatedUserEmailNotifications($value, $key): void
    {
        if (! array_key_exists($key, $this->userEmailNotifications)) {
            return;
        }

        $this->persistUserMailSettings();
    }

    public function saveAdminEmail(): void
    {
        Setting::updateOrCreate(
            ['key' => 'admin_email', 'type' => 'mails'],
            ['value' => $this->adminEmail]
        );

        $this->dispatch('showAlert', 'Admin E-Mail Adresse wurde gespeichert.', 'success');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function decodeToBool($rawValue, bool $default = false): bool
    {
        // Wert könnte plain "0"/"1", "true"/"false", bool, oder JSON-bool sein.
        $decoded = json_decode($rawValue, true);
        if (is_array($decoded)) {
            // historischer Fall: Arrays mit 'enabled' oder 'default'
            if (array_key_exists('enabled', $decoded)) {
                return (bool) $decoded['enabled'];
            }
            if (array_key_exists('default', $decoded)) {
                return (bool) $decoded['default'];
            }
            return $default;
        }

        // Wenn json_decode null liefert, kann es sein, dass rawValue schon bool/str ist
        $value = $decoded ?? $rawValue;

        if (is_bool($value)) {
            return $value;
        }

        // akzeptiert "1"/"0", "true"/"false", 1/0
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function getAdminDefault(string $key): bool
    {
        return (bool) ($this->adminEmailNotificationMeta[$key]['default'] ?? false);
    }

    private function getUserDefault(string $key): bool
    {
        return (bool) ($this->userEmailNotificationMeta[$key]['default'] ?? false);
    }

    private function persistAdminMailSettings(): void
    {
        foreach ($this->adminEmailNotifications as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key, 'type' => 'mails'],
                ['value' => json_encode((bool) $value)]
            );
        }
    }

    private function persistUserMailSettings(): void
    {
        foreach ($this->userEmailNotifications as $key => $enabled) {
            Setting::updateOrCreate(
                ['key' => $key, 'type' => 'mails'],
                ['value' => json_encode((bool) $enabled)]
            );
        }
    }

    // ------------------------------------------------------------------
    // Render
    // ------------------------------------------------------------------

    public function render()
    {
        return view('livewire.admin-config')->layout('layouts.master');
    }
}
