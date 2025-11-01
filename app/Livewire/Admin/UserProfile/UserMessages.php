<?php

namespace App\Livewire\Admin\UserProfile;

use Livewire\Component;
use App\Models\Message;
use App\Models\User;

class UserMessages extends Component
{
    public User $user;
    public string $search = '';

    public function render()
    {
        $messages = Message::query()
            ->where(function ($q) {
                $q->where('from_user', $this->user->id)
                  ->orWhere('to_user', $this->user->id);
            })
            ->when($this->search, fn($q) =>
                $q->where(function ($sub) {
                    $sub->where('subject', 'like', "%{$this->search}%")
                        ->orWhere('message', 'like', "%{$this->search}%");
                })
            )
            ->latest('id')
            ->with(['sender', 'recipient', 'files'])
            ->get();

        return view('livewire.admin.user-profile.user-messages', [
            'messages' => $messages,
        ]);
    }
}
