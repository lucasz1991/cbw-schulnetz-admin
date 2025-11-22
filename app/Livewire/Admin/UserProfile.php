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

    /**
     * UVS-Daten aktualisieren
     *
     * - Wenn $personId übergeben wird: nur diese Person updaten
     * - Wenn keine ID: alle Personen des Users (persons oder person) updaten
     */
    public function uvsApiUpdate($personId = null)
    {
        $this->loadUser();

        // Personen-Collection bauen (unterstützt sowohl persons als auch person)
        $persons = collect();

        if ($personId) {
            // Nur eine bestimmte Person updaten (z.B. aus Button im Person-Card)
            $person = $this->user->persons
                ? $this->user->persons->firstWhere('id', $personId)
                : null;

            // Fallback: alte person()-Relation
            if (!$person && $this->user->person && $this->user->person->id == $personId) {
                $person = $this->user->person;
            }

            if (!$person) {
                $this->dispatch('showAlert', 'Person für UVS-Update nicht gefunden.', 'error');
                return;
            }

            $persons = collect([$person]);
        } else {
            // Keine ID übergeben: alle Personen des Users updaten
            if ($this->user->persons && $this->user->persons->count() > 0) {
                $persons = $this->user->persons;
            } elseif ($this->user->person) {
                $persons = collect([$this->user->person]);
            } else {
                $this->dispatch('showAlert', 'Benutzer oder Person nicht gefunden.', 'error');
                return;
            }
        }

        // Jetzt alle gesammelten Personen updaten
        $updatedCount = 0;

        foreach ($persons as $person) {
            try {
                // nutzt deine bestehende apiupdate()-Methode im Person-Model
                $person->apiupdate();
                $updatedCount++;
            } catch (\Throwable $e) {
                // nur Soft-Fehler, Admin soll weiterarbeiten können
                \Log::error('UVS-API-Update für Person fehlgeschlagen', [
                    'user_id'   => $this->user->id,
                    'person_id' => $person->id ?? null,
                    'err'       => $e->getMessage(),
                ]);
            }
        }

        $this->loadUser();

        if ($updatedCount === 1) {
            $this->dispatch('showAlert', 'UVS-Daten der Person wurden aktualisiert.', 'success');
        } elseif ($updatedCount > 1) {
            $this->dispatch('showAlert', "UVS-Daten von {$updatedCount} Personen wurden aktualisiert.", 'success');
        } else {
            $this->dispatch('showAlert', 'UVS-Daten konnten nicht aktualisiert werden.', 'error');
        }
    }
    
    public function render()
    {
        return view('livewire.admin.user-profile', [
            'user' => $this->user,
        ])->layout('layouts.master');
    }
}
