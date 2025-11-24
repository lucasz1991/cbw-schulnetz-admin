<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AdminTask;
use Illuminate\Support\Facades\Auth;

class AdminTasksList extends Component
{
    use WithPagination;

    // Filter
    public ?int $filterStatus   = null; // null = alle
    public ?int $filterPriority = null; // null = alle
    public bool $onlyMine       = false;

    protected $queryString = [
        'filterStatus'   => ['except' => null],
        'filterPriority' => ['except' => null],
        'onlyMine'       => ['except' => false],
        'page'           => ['except' => 1],
    ];

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

    public function assignToMe(int $taskId): void
    {
        $task = AdminTask::findOrFail($taskId);
        $task->assignTo(Auth::id());

        $this->resetPage();
        $this->dispatch('showAlert', 'Aufgabe erfolgreich übernommen.', 'success');
    }

    public function markAsCompleted(int $taskId): void
    {
        $task = AdminTask::findOrFail($taskId);
        $task->complete();

        $this->resetPage();
        $this->dispatch('showAlert', 'Aufgabe erfolgreich abgeschlossen.', 'success');
    }

    /*
     |--------------------------------------------------------------------------
     | Render
     |--------------------------------------------------------------------------
     */

    public function render()
    {
        $query = AdminTask::with(['creator', 'assignedAdmin', 'context'])
            ->withStatus($this->filterStatus)
            ->withPriority($this->filterPriority)
            ->orderBy('status')
            ->orderBy('priority')       // Hoch vor niedrig
            ->orderByDesc('created_at');

        if ($this->onlyMine) {
            $query->forAdmin(Auth::id());
        }

        $tasks     = $query->paginate(20);
        $openCount = AdminTask::open()->count();

        return view('livewire.admin.admin-tasks-list', [
            'tasks'     => $tasks,
            'openCount' => $openCount,
        ])->layout('layouts.master');
    }
}
