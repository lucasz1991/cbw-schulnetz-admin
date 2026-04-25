<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;
use App\Models\User;
use App\Models\Person;

use App\Models\Mail;
use Illuminate\Support\Facades\DB;
use Livewire\WithFileUploads;

use Illuminate\Support\Facades\Gate;

class Users extends Component
{
    use WithPagination, WithoutUrlPagination;
    use WithFileUploads;

    public $search = '';
    public $sortBy = 'last_activity';
    public $sortDirection = 'desc';
    public $openUserId = null;
    public $usersList;
    public $selectedUsers = [];
    public $selectAll = false;
    public $action = null;
    public $hasUsers;

    public $userTypeFilter = '';

    protected $queryString = ['search', 'sortBy', 'sortDirection', 'userTypeFilter'];

    public function mount(): void
    {
        Gate::authorize('users.view');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingUserTypeFilter()
    {
        $this->resetPage();
    }

    public function sortByField($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function apiUpdateUsers()
    {
        $this->validate([
            'selectedUsers' => 'required|array',
            'selectedUsers.*' => 'exists:users,id',
        ]);

        foreach ($this->selectedUsers as $userId) {
            $user = User::find($userId);
            if ($user) {
                foreach ($user->persons as $person) {
                    $person->apiUpdate();
                }
            }
        }

        $this->dispatch('swal:toast', type: 'success', text: 'Benutzer werden nun über die UVS API aktualisiert. Dies kann einige Zeit in Anspruch nehmen.');
    }

    public function activateUsers()
    {
        $totalUsers = count($this->selectedUsers);
        $allGood = false; // Wird true, wenn mindestens ein Benutzer aktiviert wurde
        $allActive = true; // Standardmäßig davon ausgehen, dass alle Benutzer aktiv sind

        foreach ($this->selectedUsers as $index => $userId) {
            $user = User::find($userId);
            if ($user && ! $user->status) {
                $allActive = false;
                $this->progress = (($index + 1) / $totalUsers) * 100;
                $user->update(['status' => true]);
                $allGood = true;
            }
        }

        if ($allActive) {
            $this->dispatch('swal:toast', type: 'info', text: 'Alle ausgewählten Benutzer sind bereits aktiv.');
        } elseif ($allGood) {
            $this->dispatch('swal:toast', type: 'success', text: 'Benutzer erfolgreich aktiviert und verarbeitet.');
        } else {
            $this->dispatch('swal:toast', type: 'error', text: 'Fehler beim Aktivieren der Benutzer.');
        }

        $this->progress = 0; // Fortschrittsanzeige zurücksetzen
    }

    public function deactivateUsers()
    {
        $totalUsers = count($this->selectedUsers);
        $allGood = false; // Wird true, wenn mindestens ein Benutzer deaktiviert wurde
        $allInactive = true; // Standardmäßig davon ausgehen, dass alle Benutzer inaktiv sind

        foreach ($this->selectedUsers as $index => $userId) {
            $user = User::find($userId);
            if ($user && $user->status) {
                $allInactive = false;
                $this->progress = (($index + 1) / $totalUsers) * 100;
                $user->update(['status' => false]);
                $allGood = true;
            }
        }

        if ($allInactive) {
            $this->dispatch('swal:toast', type: 'info', text: 'Alle ausgewählten Benutzer sind bereits inaktiv.');
        } elseif ($allGood) {
            $this->dispatch('swal:toast', type: 'success', text: 'Benutzer erfolgreich deaktiviert und verarbeitet.');
        } else {
            $this->dispatch('swal:toast', type: 'error', text: 'Fehler beim Deaktivieren der Benutzer.');
        }

        $this->progress = 0; // Fortschrittsanzeige zurücksetzen
    }

    public function activateUser($userId)
    {
        $user = User::find($userId);

        if ($user && ! $user->status) {
            $user->update(['status' => true]);
            $this->dispatch('swal:toast', type: 'success', text: 'Benutzer erfolgreich aktiviert.');
        } else {
            $this->dispatch('swal:toast', type: 'info', text: 'Benutzer ist bereits aktiv.');
        }
    }

    public function deactivateUser($userId)
    {
        $user = User::find($userId);

        if ($user && $user->status) {
            $user->update(['status' => false]);
            $this->dispatch('swal:toast', type: 'success', text: 'Benutzer erfolgreich deaktiviert.');
        } else {
            $this->dispatch('swal:toast', type: 'info', text: 'Benutzer ist bereits inaktiv.');
        }
    }

    protected function updateHasUsers()
    {
        $this->hasUsers = User::query()
            ->where('role', 'guest')
            ->where(function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%')
                    ->orWhere('created_at', 'like', '%' . $this->search . '%')
                    ->orWhereHas('person', function ($personQuery) {
                        $personQuery->where('nachname', 'like', '%' . $this->search . '%')
                            ->orWhere('vorname', 'like', '%' . $this->search . '%');
                    });
            })
            ->exists();
    }

    public function openMailModal($userId = null)
    {
        if ($userId) {
            $this->dispatch('openMailModal', $userId);
        } else {
            // Prüfe, ob Benutzer für die Massenverarbeitung ausgewählt wurden
            if (count($this->selectedUsers) === 0) {
                $this->dispatch('swal:toast', type: 'info', text: 'Bitte wähle mindestens einen Benutzer aus, um eine Mail zu senden.');
                return;
            }
            $this->dispatch('openMailModal', $this->selectedUsers);
        }
    }

    public function toggleSelectAll()
    {
        $this->selectAll = ! $this->selectAll;

        if ($this->selectAll) {
            // Alle Benutzer laden und IDs zur `selectedUsers`-Liste hinzufügen
            $this->selectedUsers = User::query()
                ->where('role', 'guest')
                ->where(function ($query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhere('created_at', 'like', '%' . $this->search . '%')
                        ->orWhereHas('person', function ($personQuery) {
                            $personQuery->where('nachname', 'like', '%' . $this->search . '%')
                                ->orWhere('vorname', 'like', '%' . $this->search . '%');
                        });
                })
                ->pluck('id')
                ->toArray();
        } else {
            // Auswahl aufheben
            $this->selectedUsers = [];
        }
    }

    public function toggleUserSelection($userId)
    {
        if (in_array($userId, $this->selectedUsers)) {
            $this->selectedUsers = array_filter($this->selectedUsers, fn ($id) => $id != $userId);
        } else {
            $this->selectedUsers[] = $userId;
        }
    }

    public function render()
    {
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $usersQuery = User::query()
            ->with('person')
            ->when($this->userTypeFilter, fn ($query) =>
                $query->where('role', $this->userTypeFilter)
            )
            ->whereIn('role', ['guest', 'tutor'])
            ->where(function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%')
                    ->orWhere('created_at', 'like', '%' . $this->search . '%')
                    ->orWhereHas('person', function ($personQuery) {
                        $personQuery->where('nachname', 'like', '%' . $this->search . '%')
                            ->orWhere('vorname', 'like', '%' . $this->search . '%');
                    });
            });

        if ($this->sortBy === 'last_activity') {
            $usersQuery
                ->orderBy(
                    DB::table('activity_log')
                        ->selectRaw('MAX(created_at)')
                        ->whereColumn('activity_log.causer_id', 'users.id')
                        ->where('activity_log.causer_type', User::class),
                    $sortDirection
                )
                ->orderBy('users.name', 'asc');
        } elseif ($this->sortBy === 'name') {
            $usersQuery
                ->orderBy(
                    Person::select('nachname')
                        ->whereColumn('persons.user_id', 'users.id')
                        ->limit(1),
                    $sortDirection
                )
                ->orderBy(
                    Person::select('vorname')
                        ->whereColumn('persons.user_id', 'users.id')
                        ->limit(1),
                    $sortDirection
                )
                ->orderBy('users.name', $sortDirection);
        } elseif (in_array($this->sortBy, ['email', 'created_at'], true)) {
            $usersQuery->orderBy('users.' . $this->sortBy, $sortDirection);
        } else {
            $usersQuery->orderBy('users.name', $sortDirection);
        }

        $usersList = $usersQuery
            ->paginate(10)
            ->withQueryString()
            ->setPath(url('/admin/users'));

        $this->updateHasUsers();

        return view('livewire.admin.users', [
            'users' => $usersList,
        ])->layout('layouts.master');
    }
}
