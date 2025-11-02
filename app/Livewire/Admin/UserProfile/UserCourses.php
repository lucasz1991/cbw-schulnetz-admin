<?php

namespace App\Livewire\Admin\UserProfile;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;

class UserCourses extends Component
{
    use WithPagination;

    public User $user;

    public string $search = '';
    public int $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
        'page'   => ['except' => 1],
    ];

    public function mount(User $user)
    {
        $this->user = $user;
    }

    public function updatingSearch() { $this->resetPage(); }

    public function placeholder()
    {
        return <<<'HTML'
            <div role="status" class="h-32 w-full relative animate-pulse">
                    <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 transition-opacity">
                        <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2 shadow">
                            <span class="loader"></span>
                            <span class="text-sm text-gray-700">wird geladen…</span>
                        </div>
                    </div>
            </div>
        HTML;
    }

    public function render()
    {
        $courses = null;

        if ($this->user->person) {
            $pivot = 'course_participant_enrollments';

            $query = $this->user->person->courses()
                ->select('courses.*')
                ->orderBy('courses.planned_start_date', 'desc');

            if ($this->search !== '') {
                $s = '%'.$this->search.'%';
                $query->where(function ($qq) use ($s, $pivot) {
                    $qq->where('courses.title', 'like', $s)
                       ->orWhere('courses.klassen_id', 'like', $s)
                       ->orWhere('courses.termin_id', 'like', $s)
                       ->orWhere("$pivot.klassen_id", 'like', $s)
                       ->orWhere("$pivot.termin_id", 'like', $s)
                       ->orWhere("$pivot.kurzbez_ba", 'like', $s)
                       ->orWhere("$pivot.status", 'like', $s);
                });
            }

            $courses = $query->paginate($this->perPage);
        }

        return view('livewire.admin.user-profile.user-courses', [
            'courses' => $courses,
        ]);
    }
}
