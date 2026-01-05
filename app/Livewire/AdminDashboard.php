<?php

namespace App\Livewire;

use Livewire\Component;

class AdminDashboard extends Component
{
    public bool $autoRefresh = true;

    // Rollen/Visibility (einfach & zentral)
    public bool $canSeeAnalytics = false;

    public function mount(): void
    {
        $role = auth()->user()->role ?? null;

        $isSuperAdmin = in_array($role, ['super_admin', 'superadmin', 'root'], true);
        $isAdmin      = in_array($role, ['admin'], true);

        $this->canSeeAnalytics = $isSuperAdmin || $isAdmin;
    }

    public function render()
    {
        return view('livewire.admin-dashboard')->layout('layouts.master');
    }

    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = ! $this->autoRefresh;

        /**
         * OPTIONAL:
         * Wenn du willst, können Widgets dieses Event “hören” und intern refreshen
         * (statt wire:poll im Container).
         */
        $this->dispatch('dashboard:autorefresh', enabled: $this->autoRefresh);
    }
}
