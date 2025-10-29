<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\User;
use App\Models\Mail;

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
        $this->userId = $userId;
        $this->loadUser();
    }

    public function loadUser()
    {
        $this->user = User::findOrFail($this->userId);
    }

    public function activateUser()
    {

        if ($this->user && !$this->user->status) {
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

    public function openMailModal($userId = null)
    {
        if ($userId) {
            $this->dispatch('openMailModal', $userId );
        } else {
            // Prüfe, ob Benutzer für die Massenverarbeitung ausgewählt wurden
            if (count($this->selectedUsers) === 0) {
                $this->dispatch('showAlert', 'Bitte wähle mindestens einen Benutzer aus, um eine Mail zu senden.', 'info');
                return;
            }
            $this->dispatch('openMailModal', $this->selectedUsers );
        }
    }

    public function uvsApiUpdate()
    {
        if ($this->user && $this->user->person) {
            $this->user->person->apiupdate();
            $this->dispatch('showAlert', 'UVS-Daten wurden aktualisiert.', 'success');
            $this->loadUser(); // Benutzer neu laden, um aktualisierte Personendaten zu erhalten
        } else {
            $this->dispatch('showAlert', 'Benutzer oder Person nicht gefunden.', 'error');
        }
    }
    

    public function render()
    {
        return view('livewire.admin.user-profile', [
            'user' => $this->user,
        ])->layout('layouts.master');
    }
}
