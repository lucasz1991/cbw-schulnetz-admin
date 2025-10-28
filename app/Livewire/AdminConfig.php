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
        'new_user' => [
            'label' => 'Neuer Benutzer registriert',
            'description' => 'Sie werden benachrichtigt, sobald sich ein neuer Teilnehmer oder Dozent im Schulnetz registriert hat.',
            'default' => true,
        ],
        'new_course_created' => [
            'label' => 'Neuer Kurs angelegt',
            'description' => 'Sie erhalten eine Nachricht, wenn ein neuer Kurs oder ein neuer Bildungsgang im System erstellt wurde.',
            'default' => false,
        ],
        'daily_error_report' => [
            'label' => 'Täglicher Systemfehler-Bericht',
            'description' => 'Erhalten Sie eine tägliche Zusammenfassung von aufgetretenen Fehlern oder Warnungen im Systemlog.',
            'default' => false,
        ],
        'pending_approval' => [
            'label' => 'Neue Freigabe erforderlich',
            'description' => 'Benachrichtigung, wenn neue Inhalte, Kurse oder Dokumente auf eine administrative Freigabe warten.',
            'default' => true,
        ],
        'user_feedback_received' => [
            'label' => 'Neues Benutzerfeedback eingegangen',
            'description' => 'Sie werden informiert, wenn ein Teilnehmer oder Dozent neues Feedback oder eine Bewertung abgegeben hat.',
            'default' => false,
        ],
    ];

    // Admin-Notifications: Werte (nur bools; werden geladen/gespeichert)
    public $adminEmailNotifications = [
        'new_user' => true,
        'new_course_created' => false,
        'daily_error_report' => false,
        'pending_approval' => true,
        'user_feedback_received' => false,
    ];

    // ------------------------------------------------------------------
    // User-Notifications: META (statisch, NICHT überschreiben)
    // ------------------------------------------------------------------
    public $userEmailNotificationMeta = [
        'reminder_start_tomorrow' => [
            'label' => 'Kurs startet morgen',
            'description' => 'Sie erhalten eine Erinnerung, wenn Ihr Kurs am nächsten Tag beginnt.',
            'default' => false,
        ],
        'reminder_ratings_open_tomorrow' => [
            'label' => 'Bewertungen öffnen morgen',
            'description' => 'Sie werden benachrichtigt, sobald ab morgen Bewertungen für Ihren Kurs möglich sind.',
            'default' => false,
        ],
        'reminder_exam_tomorrow' => [
            'label' => 'Klausur findet morgen statt',
            'description' => 'Sie erhalten einen Hinweis am Tag vor Ihrer Prüfung.',
            'default' => false,
        ],
        // Falls du die drei zusätzlichen aus früherer Antwort wieder nutzen willst:
        'reminder_day_started' => [
            'label' => 'Unterricht hat begonnen',
            'description' => 'Erinnerung am Morgen eines Kurstages, dass der Unterricht gestartet ist.',
            'default' => false,
        ],
        'reminder_new_material' => [
            'label' => 'Neues Unterrichtsmaterial',
            'description' => 'Benachrichtigung, sobald Dozenten neue Dateien oder Lernmaterialien hochladen.',
            'default' => false,
        ],
        'reminder_course_ending_soon' => [
            'label' => 'Kurs endet bald',
            'description' => 'Erinnerung einige Tage vor Kursende, um offene Aufgaben oder Bewertungen abzuschließen.',
            'default' => false,
        ],
    ];

    // User-Notifications: Werte (nur bools; werden geladen/gespeichert)
    public $userEmailNotifications = [
        'reminder_start_tomorrow' => false,
        'reminder_ratings_open_tomorrow' => false,
        'reminder_exam_tomorrow' => false,
        'reminder_day_started' => false,
        'reminder_new_material' => false,
        'reminder_course_ending_soon' => false,
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
        foreach ($this->adminEmailNotifications as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key, 'type' => 'mails'],
                ['value' => json_encode((bool) $value)]
            );
        }

        $this->dispatch('showAlert', 'Admin E-Mail Einstellungen wurden gespeichert.', 'success');
    }

    public function saveUserMailSettings(): void
    {
        foreach ($this->userEmailNotifications as $key => $enabled) {
            Setting::updateOrCreate(
                ['key' => $key, 'type' => 'mails'],
                ['value' => json_encode((bool) $enabled)]
            );
        }

        $this->dispatch('showAlert', 'Benutzer E-Mail Einstellungen wurden gespeichert.', 'success');
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

    // ------------------------------------------------------------------
    // Render
    // ------------------------------------------------------------------

    public function render()
    {
        return view('livewire.admin-config')->layout('layouts.master');
    }
}
