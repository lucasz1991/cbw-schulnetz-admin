<?php

namespace App\Livewire\Admin;

use App\Models\AdminTask;
use App\Models\ReportBook;
use App\Services\ApiUvs\AssetsApiServices\InstitutionsLoadService;
use App\Services\ReportBooks\LocationPolicy;
use App\Services\ReportBooks\AdminTaskVisibility;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class AdminTasksList extends Component
{
    use WithPagination;

    public $search = '';
    public $filterStatus = AdminTask::STATUS_OPEN;
    public $filterPriority = null;
    public $filterInstitution = null;
    public bool $onlyMine = false;

    public $institutionOptions = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => null],
        'filterPriority' => ['except' => null],
        'filterInstitution' => ['except' => null],
        'onlyMine' => ['except' => false],
        'page' => ['except' => 1],
    ];

    protected $listeners = [
        'taskCompleted' => '$refresh',
        'taskAssigned' => '$refresh',
    ];

    public function mount(): void
    {
        Gate::authorize('jobs.view');

        $this->filterStatus = AdminTask::STATUS_OPEN;
        $this->institutionOptions = app(InstitutionsLoadService::class)->getInstitutionOptions();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPriority(): void
    {
        $this->resetPage();
    }

    public function updatingFilterInstitution(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyMine(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        Gate::authorize('jobs.view');
        $status = is_numeric($this->filterStatus) ? (int) $this->filterStatus : null;
        $priority = is_numeric($this->filterPriority) ? (int) $this->filterPriority : null;
        $institution = is_numeric($this->filterInstitution) ? (int) $this->filterInstitution : null;
        $search = trim((string) $this->search);
        $query = AdminTask::with(['creator.person', 'assignedAdmin', 'context'])
            ->where('task_type', 'reportbook_review')
            ->withStatus($status)
            ->withPriority($priority)
            ->orderBy('status')
            ->orderBy('priority')
            ->orderByDesc('created_at');

        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(function ($q) use ($like) {
                $q->where('task_type', 'like', $like)
                    ->orWhereRaw(
                        "CASE task_type
                            WHEN 'reportbook_review' THEN 'Berichtsheft prüfen'
                            WHEN 'user_request_review' THEN 'Teilnehmer Antrag'
                            ELSE 'Unbekannte Aufgabe'
                        END LIKE ?",
                        [$like]
                    )
                    ->orWhereHasMorph('context', [ReportBook::class], function ($contextQuery) use ($like) {
                        $contextQuery->where('title', 'like', $like)
                            ->orWhereHas('course', function ($courseQuery) use ($like) {
                                $courseQuery->where('title', 'like', $like)
                                    ->orWhereRaw(
                                        "JSON_UNQUOTE(JSON_EXTRACT(source_snapshot, '$.course.kurzbez')) LIKE ?",
                                        [$like]
                                    )
                                    ->orWhereRaw(
                                        "CONCAT(COALESCE(title, ''), ' - ', COALESCE(JSON_UNQUOTE(JSON_EXTRACT(source_snapshot, '$.course.kurzbez')), '')) LIKE ?",
                                        [$like]
                                    );
                            });
                    })
                    ->orWhereHas('creator', function ($creatorQuery) use ($like) {
                        $creatorQuery->where('name', 'like', $like)
                            ->orWhereHas('person', function ($personQuery) use ($like) {
                                $personQuery->where('vorname', 'like', $like)
                                    ->orWhere('nachname', 'like', $like)
                                    ->orWhereRaw(
                                        "CONCAT(COALESCE(vorname, ''), ' ', COALESCE(nachname, '')) LIKE ?",
                                        [$like]
                                    );
                            });
                    });
            });
        }

        if ($this->onlyMine) {
            $query->forAdmin(Auth::id());
        }

        $tasks = AdminTaskVisibility::apply($query, $institution)->paginate(20);
        foreach ($tasks as $task) {
            $task->setAttribute('location_policy_mode', $task->context instanceof ReportBook
                ? LocationPolicy::forBook($task->context)['mode'] : 'normal');
        }
        $openCount = AdminTaskVisibility::apply(AdminTask::open()
            ->where('task_type', 'reportbook_review'), $institution)->count();

        return view('livewire.admin.admin-tasks-list', [
            'tasks' => $tasks,
            'openCount' => $openCount,
        ])->layout('layouts.master');
    }

}
