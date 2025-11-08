<?php

namespace App\Livewire\Admin\UserProfile;

use Livewire\Component;
use App\Models\UserRequest;

class RequestDetailModal extends Component
{
    public bool $showModal = false;

    /** Fürs Blade */
    public ?UserRequest $request = null;

    /** Admin-Kommentar (für approve/reject/cancel) */
    public ?string $adminComment = null;

    /** Events aus der Liste: $this->dispatch('admin:open-request-detail', id: $id) */
    protected $listeners = [
        'admin:open-request-detail' => 'openById',
    ];

    public function openById($payload): void
    {
        $id = (int)($payload['id'] ?? $payload);

        $this->request = UserRequest::with(['files', 'user'])->findOrFail($id);
        $this->adminComment = null;
        $this->showModal = true;
    }

    public function close(): void
    {
        $this->reset(['showModal', 'adminComment']);
    }

    public function markInReview(): void
    {
        if (!$this->request) return;

        $this->request->update([
            'status'       => UserRequest::STATUS_IN_REVIEW,
            'admin_comment'=> $this->adminComment,
        ]);

        $this->dispatch('toast', ['type' => 'info', 'text' => 'Als „In Prüfung“ markiert.']);
        $this->dispatch('refreshUserRequests');
    }

    public function approve(): void
    {
        if (!$this->request) return;

        $this->request->approve($this->adminComment);
        $this->dispatch('toast', ['type' => 'success', 'text' => 'Antrag genehmigt.']);
        $this->dispatch('refreshUserRequests');
        $this->close();
    }

    public function reject(): void
    {
        if (!$this->request) return;

        $this->request->reject($this->adminComment);
        $this->dispatch('toast', ['type' => 'warning', 'text' => 'Antrag abgelehnt.']);
        $this->dispatch('refreshUserRequests');
        $this->close();
    }

    public function cancel(): void
    {
        if (!$this->request) return;

        $this->request->cancel($this->adminComment);
        $this->dispatch('toast', ['type' => 'info', 'text' => 'Antrag storniert.']);
        $this->dispatch('refreshUserRequests');
        $this->close();
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
