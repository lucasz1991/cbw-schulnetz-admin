<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'person_id',
        'result',
        'status',
        'updated_by',
        'lecturer_assignment_id',
        'lecturer_plan_revision',
    ];

    /**
     * Beziehungen
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
