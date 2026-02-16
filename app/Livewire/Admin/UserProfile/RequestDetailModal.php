<?php

namespace App\Livewire\Admin\UserProfile;

use Livewire\Component;
use App\Models\UserRequest;

class RequestDetailModal extends Component
{
    public bool $showModal = false;

    /** Fürs Blade */
    public ?UserRequest $request = null;

    /** Events aus der Liste: $this->dispatch('admin:open-request-detail', id: $id) */
    protected $listeners = [
        'admin:open-request-detail' => 'openById',
    ];

    public function openById($payload): void
    {
        $id = (int)($payload['id'] ?? $payload);

        $this->request = UserRequest::with(['files', 'user'])->findOrFail($id);
        $this->showModal = true;
    }

    public function close(): void
    {
        $this->reset(['showModal']);
    }

    public function delete(): void
    {
        if (!$this->request) return;

        $this->request->delete();
        $this->dispatch('toast', ['type' => 'success', 'text' => 'Antrag gelöscht.']);
        $this->dispatch('refreshUserRequests');
        $this->close();
    }

    public function render()
    {
        return view('livewire.admin.user-profile.request-detail-modal');
    }
}
