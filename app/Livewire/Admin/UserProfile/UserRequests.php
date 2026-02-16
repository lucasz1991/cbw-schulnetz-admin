<?php

namespace App\Livewire\Admin\UserProfile;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Database\Eloquent\Builder;

class UserRequests extends Component
{
    use WithPagination;

    /** Der betrachtete Benutzer (Profil) */
    public User $user;

    /** Filter & UI */
    public string $search = '';
    public ?string $type = null;        // absence|makeup|external_makeup|general
    public int $perPage = 10;

    /** Sortierung */
    public string $sortField = 'submitted_at';
    public string $sortDirection = 'desc';

    protected $queryString = [
        'search'        => ['except' => ''],
        'type'          => ['except' => null],
        'sortField'     => ['except' => 'submitted_at'],
        'sortDirection' => ['except' => 'desc'],
        'perPage'       => ['except' => 10],
    ];

    protected $listeners = ['refreshUserRequests' => '$refresh'];

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function updatingSearch(): void     { $this->resetPage(); }
    public function updatingType(): void       { $this->resetPage(); }
    public function updatingPerPage(): void    { $this->resetPage(); }
    public function updatingSortField(): void  { $this->resetPage(); }
    public function updatingSortDirection(): void { $this->resetPage(); }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->type = null;
        $this->resetPage();
    }

    protected function baseQuery(): Builder
    {
        return UserRequest::query()
            ->where('user_id', $this->user->id)
            ->when($this->type, fn (Builder $q) => $q->where('type', $this->type))
            ->when($this->search, function (Builder $q) {
                $term = '%'.$this->search.'%';
                $q->where(function (Builder $qq) use ($term) {
                    $qq->where('title', 'like', $term)
                       ->orWhere('message', 'like', $term)
                       ->orWhere('class_code', 'like', $term)
                       ->orWhere('module_code', 'like', $term)
                       ->orWhere('instructor_name', 'like', $term);
                });
            });
    }

    public function getStatsProperty(): array
    {
        $q = $this->baseQuery();
        return [
            'total'    => (clone $q)->count(),
            'pending'  => (clone $q)->where('status', UserRequest::STATUS_PENDING)->count(),
            'approved' => (clone $q)->where('status', UserRequest::STATUS_APPROVED)->count(),
            'rejected' => (clone $q)->where('status', UserRequest::STATUS_REJECTED)->count(),
        ];
    }

    public function render()
    {
        $requests = $this->baseQuery()
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.user-profile.user-requests', [
            'requests' => $requests,
        ]);
    }
}
