<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Team;

class Employees extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    // Auswahl / Bulk
    public array $selectedEmployees = [];
    public bool $selectAll = false;

    // Filter / Suche / Sortierung
    public string $search = '';
    public ?int $teamId = null; // Team-Filter (optional)
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';
    public int $perPage = 15;

    // Zähler (Anzeige)
    public int $employeesTotal = 0;

    protected $listeners = [
        'employeeSaved' => '$refresh',
    ];

    public function updatingSearch(){ $this->resetPage(); }
    public function updatingTeamId(){ $this->resetPage(); }
    public function updatingPerPage(){ $this->resetPage(); }

    public function sort($col)
    {
        if ($this->sortBy === $col) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $col;
            $this->sortDir = 'asc';
        }
    }

    public function toggleSelectAll(): void
    {
        $this->selectAll = ! $this->selectAll;
        if ($this->selectAll) {
            $this->selectedEmployees = $this->currentIds();
        } else {
            $this->selectedEmployees = [];
        }
    }

    public function currentIds(): array
    {
        return $this->employees->pluck('id')->map(fn($i)=>(int)$i)->all();
    }

    public function openCreate(): void
    {
        $this->dispatch('open-employee-form')->to(\App\Livewire\Admin\Employees\EmployeeFormModal::class);
    }

    public function openEdit(int $id): void
    {
        $this->dispatch('open-employee-form', id: $id)->to(\App\Livewire\Admin\Employees\EmployeeFormModal::class);
    }

    // Beispiel-Bulk-Aktionen (Platzhalter)
    public function exportSelected(): void
    {
        // TODO: Export-Logik
        $this->dispatch('swal:toast', type:'info', title:'Export', text:'Export vorbereitet.');
    }
    public function clearSelection(): void
    {
        $this->selectedEmployees = [];
        $this->selectAll = false;
    }

public function getEmployeesProperty()
{
    $allowedRoles = ['admin','staff'];

    $base = User::query()
        ->with('currentTeam')
        ->whereIn('role', $allowedRoles)
        ->where('current_team_id', '!=', 1) // 👈 User mit Team-ID 1 ausschließen
        ->when($this->search, fn($q) =>
            $q->where(function($qq){
                $s = '%'.$this->search.'%';
                $qq->where('name','like',$s)
                   ->orWhere('email','like',$s)
                   ->orWhere('id',$this->search);
            })
        )
        ->when($this->teamId, fn($q) =>
            $q->whereHas('teams', fn($qq) => $qq->where('teams.id', $this->teamId))
        );

    // Gesamtanzahl vor Pagination
    $this->employeesTotal = (clone $base)->count();

    return $base->orderBy($this->sortBy, $this->sortDir)
        ->paginate($this->perPage);
}


    public function render()
    {
$teams = Team::where('id', '!=', 1)
    ->orderBy('name')
    ->get(['id', 'name']);        // wichtig: $employees via accessor, damit currentIds() funktioniert
        $employees = $this->employees;

        return view('livewire.admin.employees', compact('employees','teams'))
            ->layout('layouts.master');
    }
}
