<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\AdminTask;
use Livewire\Component;

class OpenJobs extends Component
{
    public bool $autoRefresh = true;

    /** @var array<int, \App\Models\AdminTask> */
    public array $jobs = [];

    public int $limit = 6;

    public function mount(bool $autoRefresh = true): void
    {
        $this->autoRefresh = $autoRefresh;
        $this->loadJobs();
    }

    public function render()
    {
        return view('livewire.admin.dashboard.open-jobs');
    }

    public function loadJobs(): void
    {
        $this->jobs = AdminTask::query()
            ->where('status', AdminTask::STATUS_OPEN)
            ->with(['creator:id,name', 'assignedAdmin:id,name', 'context'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('due_at')
            ->orderBy('priority')
            ->orderByDesc('created_at')
            ->limit($this->limit)
            ->get()
            ->all();
    }
}
