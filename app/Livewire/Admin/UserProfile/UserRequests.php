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
    public ?string $status = null;      // pending|approved|rejected|canceled|in_review
    public ?string $type = null;        // absence|makeup|external_makeup|general
    public ?string $from = null;        // YYYY-MM-DD
    public ?string $to = null;          // YYYY-MM-DD
    public int $perPage = 10;

    /** Sortierung */
    public string $sortField = 'submitted_at';
    public string $sortDirection = 'desc';

    protected $queryString = [
        'search'        => ['except' => ''],
        'status'        => ['except' => null],
        'type'          => ['except' => null],
        'from'          => ['except' => null],
        'to'            => ['except' => null],
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
    public function updatingStatus(): void     { $this->resetPage(); }
    public function updatingType(): void       { $this->resetPage(); }
    public function updatingFrom(): void       { $this->resetPage(); }
    public function updatingTo(): void         { $this->resetPage(); }
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
        $this->status = null;
        $this->type = null;
        $this->from = null;
        $this->to = null;
        $this->resetPage();
    }

    public function approve(int $id, ?string $comment = null): void
    {
        $req = $this->findForUser($id);
        if (!$req) return;

        $req->approve($comment);
        $this->dispatch('toast', ['type' => 'success', 'text' => 'Antrag genehmigt.']);
        $this->dispatch('refreshUserRequests');
    }

    public function reject(int $id, ?string $comment = null): void
    {
        $req = $this->findForUser($id);
        if (!$req) return;

        $req->reject($comment);
        $this->dispatch('toast', ['type' => 'warning', 'text' => 'Antrag abgelehnt.']);
        $this->dispatch('refreshUserRequests');
    }

    public function cancel(int $id, ?string $comment = null): void
    {
        $req = $this->findForUser($id);
        if (!$req) return;

        $req->cancel($comment);
        $this->dispatch('toast', ['type' => 'info', 'text' => 'Antrag storniert.']);
        $this->dispatch('refreshUserRequests');
    }

    protected function findForUser(int $id): ?UserRequest
    {
        return UserRequest::where('user_id', $this->user->id)->find($id);
    }

    protected function baseQuery(): Builder
    {
        return UserRequest::query()
            ->where('user_id', $this->user->id)
            ->when($this->type, fn (Builder $q) => $q->where('type', $this->type))
            ->when($this->status, fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->from, fn (Builder $q) => $q->whereDate('submitted_at', '>=', $this->from))
            ->when($this->to, fn (Builder $q) => $q->whereDate('submitted_at', '<=', $this->to))
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
