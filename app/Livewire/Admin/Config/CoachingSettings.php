<?php

namespace App\Livewire\Admin\Config;

use App\Models\Setting;
use App\Services\Coaching\Access;
use Livewire\Component;

class CoachingSettings extends Component
{
    public bool $enabled = false;

    private function authorizeSettings(): void
    {
        // Same superadmin role as the API settings tab; team permissions do not grant access.
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function mount(): void
    {
        $this->authorizeSettings();
        $this->enabled = Access::enabled();
    }

    public function save(): void
    {
        $this->authorizeSettings();
        $this->validate(['enabled' => 'required|boolean']);
        Setting::setValue('coaching', 'enabled', $this->enabled);
        session()->flash('coaching_settings_saved', 'Einzelcoaching-Einstellung gespeichert.');
    }

    public function render()
    {
        $this->authorizeSettings();
        return view('livewire.admin.config.coaching-settings');
    }
}
