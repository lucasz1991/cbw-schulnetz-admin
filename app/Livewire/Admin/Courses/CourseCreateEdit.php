<?php

namespace App\Livewire\Admin\Courses;

use App\Models\Course;
use App\Models\User;
use Livewire\Component;

class CourseCreateEdit extends Component
{
    public $showModal = false;
    public $courseId;

    public $title;
    public $description;
    public $start_time;
    public $end_time;
    public $status;
    public $tutor_id;
    public $participants = [];

    public $tutors = [];

    public $possibleParticipants = [];


    protected $rules = [
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'start_time' => 'nullable|date',
        'end_time' => 'nullable|date|after_or_equal:start_time',
        'status' => 'required|in:draft,active,archived',
        'tutor_id' => 'required|exists:users,id',
        'participants' => 'nullable|array',
        'participants.*' => 'exists:users,id',

    ];

    protected $listeners = ['open-course-create-edit' => 'loadCourse'];

    public function loadCourse($courseId = null)
    {
        $this->reset(['title', 'description', 'start_time', 'end_time', 'status', 'tutor_id', 'courseId']);

        if ($courseId) {
            $course = Course::findOrFail($courseId);
            $this->courseId = $course->id;
            $this->title = $course->title;
            $this->description = $course->description;
            $this->start_time = $course->start_time?->format('Y-m-d\TH:i');
            $this->end_time = $course->end_time?->format('Y-m-d\TH:i');
            $this->status = $course->status;
            $this->tutor_id = $course->tutor_id;
$this->participants = $course->participants()->pluck('users.id')->toArray();


        }

        $this->showModal = true;
    }



    public function saveCourse()
    {
        $this->validate();

        $course = Course::updateOrCreate(
            ['id' => $this->courseId],
            [
                'title' => $this->title,
                'description' => $this->description,
                'start_time' => $this->start_time,
                'end_time' => $this->end_time,
                'status' => $this->status,
                'tutor_id' => $this->tutor_id,
            ]
        );

        // Teilnehmer syncen
        $course->participants()->sync($this->participants ?? []);


        session()->flash('message', 'Kurs gespeichert.');
        $this->showModal = false;

        $this->dispatch('refreshCourses');
    }

    public function closeModal()
    {
        $this->showModal = false;
    }

    public function render()
    {
        $this->tutors = User::where('role', 'tutor')->get();
        $this->possibleParticipants = User::where('role', 'guest')->get();

        return view('livewire.admin.courses.course-create-edit', [
            'tutors' => $this->tutors,
            'possibleParticipants' => $this->possibleParticipants,
        ]);
    }
}
