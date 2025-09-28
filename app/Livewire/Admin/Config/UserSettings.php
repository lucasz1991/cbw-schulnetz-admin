<?php

namespace App\Livewire\Admin\Config;

use Livewire\Component;
use App\Models\Setting;
use Illuminate\Validation\Rule;

class UserSettings extends Component
{
    public int $openBeforeDays = 14;   // VOR Kursstart
    public int $closeAfterDays = 7;    // NACH Kursende
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

    public function mount()
    {
        $allowed = array_keys($this->dayOptions);

        $storedOpen  = (int) (Setting::getValue('course_registration', 'open_before_start_days') ?? 14);
        $storedClose = (int) (Setting::getValue('course_registration', 'close_after_end_days') ?? 7);

        $this->openBeforeDays = in_array($storedOpen,  $allowed, true) ? $storedOpen  : 14;
        $this->closeAfterDays = in_array($storedClose, $allowed, true) ? $storedClose : 7;
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

    public function render()
    {
        return view('livewire.admin.config.user-settings');
    }
}
