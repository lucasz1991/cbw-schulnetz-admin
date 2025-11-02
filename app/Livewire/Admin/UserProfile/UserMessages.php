<?php

namespace App\Livewire\Admin\UserProfile;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;
use App\Models\Message;
use App\Models\User;

class UserMessages extends Component
{
    use WithPagination, WithoutUrlPagination;

    public User $user;
    public string $search = '';

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
}
