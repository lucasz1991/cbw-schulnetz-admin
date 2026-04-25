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
            $this->dispatch('swal:toast', type: 'success', text: 'Benutzer erfolgreich aktiviert.');
        } else {
            $this->dispatch('swal:toast', type: 'info', text: 'Benutzer ist bereits aktiv.');
        }

        $this->loadUser();
    }

    public function deactivateUser()
    {
        if ($this->user && $this->user->status) {
            $this->user->update(['status' => false]);
            $this->dispatch('swal:toast', type: 'success', text: 'Benutzer erfolgreich deaktiviert.');
        } else {
            $this->dispatch('swal:toast', type: 'info', text: 'Benutzer ist bereits inaktiv.');
        }

        $this->loadUser();
    }

    public function deleteUser()
    {
        Gate::authorize('users.edit');

        $user = User::find($this->userId);

        if (! $user) {
            $this->dispatch('swal:toast', type: 'error', text: 'Benutzer wurde nicht gefunden.');
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

            $this->dispatch('swal:toast', type: 'error', text: 'Benutzer konnte nicht gelöscht werden.');
            return;
        }

        $this->dispatch(
            'swal:toast',
            type: 'success',
            text: "Benutzer wurde gelöscht. {$detachedPersons} Person(en) wurden vom Benutzer entkoppelt."
        );

        return $this->redirectRoute('admin.users');
    }

    public function openMailModal($userId = null)
    {
        if ($userId) {
            $this->dispatch('openMailModal', $userId);
        } else {
            if (count($this->selectedUsers) === 0) {
                $this->dispatch('swal:toast', type: 'info', text: 'Bitte wähle mindestens einen Benutzer aus, um eine Mail zu senden.');
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
            $this->dispatch('swal:toast', type: 'success', text: 'UVS-Update für die Person wurde in die Queue gestellt.');
        } elseif ($queuedCount > 1) {
            $this->dispatch('swal:toast', type: 'success', text: "UVS-Update für {$queuedCount} Personen wurde in die Queue gestellt.");
        } else {
            $this->dispatch('swal:toast', type: 'error', text: 'Benutzer oder Person für das UVS-Update nicht gefunden.');
        }
    }

    public function render()
    {
        return view('livewire.admin.user-profile', [
            'user' => $this->user,
        ])->layout('layouts.master');
    }
}
