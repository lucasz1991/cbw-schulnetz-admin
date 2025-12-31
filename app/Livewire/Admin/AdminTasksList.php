<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AdminTask;
use Illuminate\Support\Facades\Auth;

class AdminTasksList extends Component
{
    use WithPagination;

    // Filter OHNE strikte Typen, damit Livewire Strings aus Query/Request setzen darf
    public $filterStatus   = null; // null = alle, Default setzen wir in mount()
    public $filterPriority = null; // null = alle
    public bool $onlyMine  = false;

    protected $queryString = [
        'filterStatus'   => ['except' => null],
        'filterPriority' => ['except' => null],
        'onlyMine'       => ['except' => false],
        'page'           => ['except' => 1],
    ];

    protected $listeners = [
        'taskCompleted' => '$refresh',
        'taskAssigned'  => '$refresh',
    ];

    public function mount(): void
    {

    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPriority(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyMine(): void
    {
        $this->resetPage();
    }

    /*
    |--------------------------------------------------------------------------
    | Render
    |--------------------------------------------------------------------------
    */

    public function render()
    {
        // Falls Livewire einen String gesetzt hat, hier „weich“ in int wandeln
        $status   = is_numeric($this->filterStatus)   ? (int) $this->filterStatus   : null;
        $priority = is_numeric($this->filterPriority) ? (int) $this->filterPriority : null;

        $query = AdminTask::with(['creator', 'assignedAdmin', 'context'])
            ->where('task_type', 'reportbook_review')
            ->withStatus($status)           // Scope nimmt ?int, PHP castet sauber
            ->withPriority($priority)
            ->orderBy('status')
            ->orderBy('priority')           // Hoch vor niedrig
            ->orderByDesc('created_at');

        if ($this->onlyMine) {
            $query->forAdmin(Auth::id());
        }

        $tasks     = $query->paginate(20);
        $openCount = AdminTask::open()->where('task_type', 'reportbook_review')->count();

        return view('livewire.admin.admin-tasks-list', [
            'tasks'     => $tasks,
            'openCount' => $openCount,
        ])->layout('layouts.master');
    }
}
