<?php

namespace App\Livewire\Admin;

use App\Models\Mail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class UserProfile extends Component
{
    public $userId;
    public $user;

    public $showMailModal = false;
    public $mailUserId = null;
    public $mailSubject = '';
    public $mailHeader = '';
    public $mailBody = '';
    public $mailLink = '';

    public function mount($userId)
    {
        Gate::authorize('users.profiles.view');
        $this->userId = $userId;
        $this->loadUser();
    }

    public function loadUser()
    {
        $this->user = User::findOrFail($this->userId);
    }

    public function activateUser()
    {
        if ($this->user && ! $this->user->status) {
            $this->user->update(['status' => true]);
            $this->dispatch('showAlert', 'Benutzer erfolgreich aktiviert.', 'success');
        } else {
            $this->dispatch('showAlert', 'Benutzer ist bereits aktiv.', 'info');
        }

        $this->loadUser();
    }

    public function deactivateUser()
    {
        if ($this->user && $this->user->status) {
            $this->user->update(['status' => false]);
            $this->dispatch('showAlert', 'Benutzer erfolgreich deaktiviert.', 'success');
        } else {
            $this->dispatch('showAlert', 'Benutzer ist bereits inaktiv.', 'info');
        }

        $this->loadUser();
    }

    public function deleteUser()
    {
        Gate::authorize('users.edit');

        $user = User::find($this->userId);

        if (! $user) {
            $this->dispatch('showAlert', 'Benutzer wurde nicht gefunden.', 'error');
            return $this->redirectRoute('admin.users');
        }

        $detachedPersons = 0;

        try {
            DB::transaction(function () use ($user, &$detachedPersons) {
                $detachedPersons = $user->detachPersons();
                $user->tokens()->delete();
                $user->deleteProfilePhoto();
                $user->delete();
            });
        } catch (\Throwable $e) {
            \Log::error('Benutzer konnte nicht gelöscht werden.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('showAlert', 'Benutzer konnte nicht gelöscht werden.', 'error');
            return;
        }

        $this->dispatch(
            'showAlert',
            "Benutzer wurde gelöscht. {$detachedPersons} Person(en) wurden vom Benutzer entkoppelt.",
            'success'
        );

        return $this->redirectRoute('admin.users');
    }

    public function openMailModal($userId = null)
    {
        if ($userId) {
            $this->dispatch('openMailModal', $userId);
        } else {
            if (count($this->selectedUsers) === 0) {
                $this->dispatch('showAlert', 'Bitte wähle mindestens einen Benutzer aus, um eine Mail zu senden.', 'info');
                return;
            }

            $this->dispatch('openMailModal', $this->selectedUsers);
        }
    }

    public function uvsApiUpdate($personId = null)
    {
        $this->loadUser();

        $queuedCount = $this->user->uvsApiUpdate($personId ? (int) $personId : null);

        if ($queuedCount === 1) {
            $this->dispatch('showAlert', 'UVS-Update für die Person wurde in die Queue gestellt.', 'success');
        } elseif ($queuedCount > 1) {
            $this->dispatch('showAlert', "UVS-Update für {$queuedCount} Personen wurde in die Queue gestellt.", 'success');
        } else {
            $this->dispatch('showAlert', 'Benutzer oder Person für das UVS-Update nicht gefunden.', 'error');
        }
    }

    public function render()
    {
        return view('livewire.admin.user-profile', [
            'user' => $this->user,
        ])->layout('layouts.master');
    }
}
