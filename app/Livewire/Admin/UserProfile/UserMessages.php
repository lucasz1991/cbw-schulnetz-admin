<?php

namespace App\Livewire\Admin\UserProfile;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserMessages extends Component
{
    use WithPagination, WithoutUrlPagination;

    public User $user;
    public string $search = '';
    public bool $showMessageModal = false;
    public ?int $selectedMessageId = null;

    protected $paginationTheme = 'tailwind';

    public function placeholder()
    {
        return <<<'HTML'
            <div role="status" class="h-32 w-full relative animate-pulse">
                    <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 transition-opacity">
                        <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2 shadow">
                            <span class="loader"></span>
                            <span class="text-sm text-gray-700">wird geladen…</span>
                        </div>
                    </div>
            </div>
        HTML;
    }

    public function render()
    {
        $messages = Message::query()
            ->where('to_user', $this->user->id) 
            ->when($this->search, fn($q) =>
                $q->where(function ($sub) {
                    $sub->where('subject', 'like', "%{$this->search}%")
                        ->orWhere('message', 'like', "%{$this->search}%");
                })
            )
            ->latest('id')
            ->with(['sender', 'files'])
            ->paginate(10);

        return view('livewire.admin.user-profile.user-messages', [
            'messages' => $messages,
        ]);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openMessage(int $messageId): void
    {
        $exists = Message::query()
            ->where('id', $messageId)
            ->where('to_user', $this->user->id)
            ->exists();

        if (! $exists) {
            return;
        }

        $this->selectedMessageId = $messageId;
        $this->showMessageModal = true;
    }

    public function closeMessageModal(): void
    {
        $this->showMessageModal = false;
        $this->selectedMessageId = null;
    }

    public function deleteMessage(int $messageId): void
    {
        if (!Auth::user()?->isAdmin()) {
            $this->dispatch('showAlert', 'Keine Berechtigung zum Löschen.', 'error');
            return;
        }

        $message = Message::query()
            ->where('id', $messageId)
            ->where('to_user', $this->user->id)
            ->with('files')
            ->first();

        if (! $message) {
            $this->dispatch('showAlert', 'Nachricht nicht gefunden.', 'error');
            return;
        }

        foreach ($message->files as $file) {
            $file->delete();
        }

        $message->delete();

        if ($this->selectedMessageId === $messageId) {
            $this->closeMessageModal();
        }

        $this->dispatch('showAlert', 'Nachricht wurde gelöscht.', 'success');
        $this->resetPage();
    }

    public function getSelectedMessageProperty(): ?Message
    {
        if (! $this->selectedMessageId) {
            return null;
        }

        return Message::query()
            ->where('id', $this->selectedMessageId)
            ->where('to_user', $this->user->id)
            ->with(['sender', 'files'])
            ->first();
    }
}
