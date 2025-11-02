<?php

namespace App\Livewire\Admin\Config;

use Livewire\Component;
use App\Models\Setting;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class UserSettings extends Component
{
    public int $openBeforeDays = 14;   // VOR Kursstart
    public int $closeAfterDays = 7;    // NACH Kursende

    /** Master Password UI-State */
    public bool $masterIsActive = false;
    public ?string $masterExpiresAt = null;

    /** Formfelder */
public ?string $newMasterPassword = null;
public ?string $newMasterPassword_confirmation = null;
    public string $validFor = '3600'; // default 1h (in Sekunden)

    public array $dayOptions = [
        3   => '3 Tage',
        5   => '5 Tage',
        7   => '1 Woche',
        14  => '2 Wochen',
        30  => '1 Monat',
        90  => '3 Monate',
        180 => '6 Monate',
        356 => '1 Jahr',
        5 * 356 => '5 Jahre',
        10 * 356 => '10 Jahre',
        20 * 356 => '20 Jahre',
    ];

    /** Gültigkeits-Optionen (Sekunden) */
    public array $validForOptions = [
        '900'    => '15 Minuten',
        '1800'   => '30 Minuten',
        '3600'   => '1 Stunde',
        '21600'  => '6 Stunden',
        '43200'  => '12 Stunden',
        '86400'  => '24 Stunden',
        '259200' => '3 Tage',
        '604800' => '7 Tage',
    ];

    public function mount()
    {
        $allowed = array_keys($this->dayOptions);

        $storedOpen  = (int) (Setting::getValue('course_registration', 'open_before_start_days') ?? 14);
        $storedClose = (int) (Setting::getValue('course_registration', 'close_after_end_days') ?? 7);

        $this->openBeforeDays = in_array($storedOpen,  $allowed, true) ? $storedOpen  : 14;
        $this->closeAfterDays = in_array($storedClose, $allowed, true) ? $storedClose : 7;

        // Master-Passwort Status laden
        $hash = Setting::getValue('auth', 'master_password_hash');
        $exp  = Setting::getValue('auth', 'master_password_expires_at');

        $this->masterExpiresAt = $exp ?: null;
        $this->masterIsActive  = $hash && $exp && Carbon::now()->lt(Carbon::parse($exp));
    }

    public function save()
    {
        $allowed = array_keys($this->dayOptions);

        $this->validate([
            'openBeforeDays' => ['required','integer', Rule::in($allowed)],
            'closeAfterDays' => ['required','integer', Rule::in($allowed)],
        ]);

        Setting::setValue('course_registration', 'open_before_start_days', $this->openBeforeDays);
        Setting::setValue('course_registration', 'close_after_end_days',   $this->closeAfterDays);

        session()->flash('success', 'Registrierungsfenster gespeichert.');
        $this->dispatch('$refresh');
    }

    public function setMasterPassword()
    {
        $this->validate([
            'newMasterPassword' => ['required','string','min:10','confirmed'],
            'validFor'          => ['required', Rule::in(array_keys($this->validForOptions))],
        ], [], [
            'newMasterPassword' => 'Master-Passwort',
            'validFor'          => 'Gültigkeitsdauer',
        ]);

        $hash = Hash::make($this->newMasterPassword);
        $expiresAt = Carbon::now()->addSeconds((int) $this->validFor)->toIso8601String();

        Setting::setValue('auth', 'master_password_hash', $hash);
        Setting::setValue('auth', 'master_password_expires_at', $expiresAt);

        // Formular leeren & Status aktualisieren
$this->reset(['newMasterPassword', 'newMasterPassword_confirmation']);
        $this->masterExpiresAt = $expiresAt;
        $this->masterIsActive  = true;

        session()->flash('success', 'Master-Passwort gesetzt.');
        $this->dispatch('$refresh');
    }

    public function revokeMasterPassword()
    {
        Setting::setValue('auth', 'master_password_hash', null);
        Setting::setValue('auth', 'master_password_expires_at', null);

        $this->masterIsActive  = false;
        $this->masterExpiresAt = null;

        session()->flash('success', 'Master-Passwort deaktiviert.');
        $this->dispatch('$refresh');
    }

    public function render()
    {
        return view('livewire.admin.config.user-settings');
    }
}
