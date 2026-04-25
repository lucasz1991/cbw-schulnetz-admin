<?php

namespace App\Livewire\Admin\Config;

use App\Models\Setting;
use Livewire\Component;

class AteraSettings extends Component
{
    public string $baseUrl = 'https://app.atera.com';
    public string $apiKey = '';
    public string $technicianEmail = 'support@cbw-weiterbildung.de';

    public function mount(): void
    {
        $this->baseUrl = Setting::getValueUncached('atera', 'base_url') ?? 'https://app.atera.com';
        $this->apiKey = Setting::getValueUncached('atera', 'api_key') ?? '';
        $this->technicianEmail = Setting::getValueUncached('atera', 'technician_email') ?? 'support@cbw-weiterbildung.de';
    }

    public function save(): void
    {
        $this->validate([
            'baseUrl' => ['required', 'url', 'max:255'],
            'apiKey' => ['required', 'string', 'max:255'],
            'technicianEmail' => ['required', 'email', 'max:255'],
        ], [
            'baseUrl.required' => 'Bitte hinterlege die Atera Basis-URL.',
            'baseUrl.url' => 'Bitte gib eine gültige URL an.',
            'apiKey.required' => 'Bitte hinterlege den Atera API-Key.',
            'technicianEmail.required' => 'Bitte hinterlege die zentrale Support-E-Mail.',
            'technicianEmail.email' => 'Bitte gib eine gültige Support-E-Mail an.',
        ]);

        Setting::setValue('atera', 'base_url', trim($this->baseUrl));
        Setting::setValue('atera', 'api_key', trim($this->apiKey));
        Setting::setValue('atera', 'technician_email', trim($this->technicianEmail));

        $this->dispatch('notify', type: 'success', message: 'Atera API-Einstellungen gespeichert.');
    }

    public function render()
    {
        return view('livewire.admin.config.atera-settings');
    }
}
