<?php

namespace App\Livewire\Admin\UserProfile;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Course;

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
        $this->user = $user->load(['persons']); // mehrere persons laden
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

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
        $courses = collect();

        // Liste aller person_ids des Users (multi-person)
        $personIds = $this->user->persons->pluck('id')->toArray();

        // ------------------------------------------------------------------
        // 📌 FALL 1: User ist Dozent → Kurse über Course.primary_tutor_person_id
        // ------------------------------------------------------------------
        if ($this->user->role === 'tutor') {

            $query = Course::whereIn('primary_tutor_person_id', $personIds)
                ->orderBy('planned_start_date', 'desc');

            if ($this->search !== '') {
                $s = '%' . $this->search . '%';

                $query->where(function ($qq) use ($s) {
                    $qq->where('courses.title', 'like', $s)
                       ->orWhere('courses.klassen_id', 'like', $s)
                       ->orWhere('courses.termin_id', 'like', $s)
                       ->orWhere('courses.vtz', 'like', $s)
                       ->orWhere('courses.room', 'like', $s);
                });
            }

            $courses = $query->paginate($this->perPage);

        }
        // ------------------------------------------------------------------
        // 📌 FALL 2: User ist Teilnehmer → Pivot-Relation über alle Persons
        // ------------------------------------------------------------------
        else {

            $pivot = 'course_participant_enrollments';

            $query = Course::query()
                ->join($pivot, "$pivot.course_id", '=', 'courses.id')
                ->whereIn("$pivot.person_id", $personIds)
                ->select('courses.*')
                ->orderBy('courses.planned_start_date', 'desc');

            if ($this->search !== '') {
                $s = '%' . $this->search . '%';

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

        // ------------------------------------------------------------------

        return view('livewire.admin.user-profile.user-courses', [
            'courses' => $courses,
        ]);
    }
}
