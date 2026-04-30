<?php

namespace App\Services\ApiUvs\CourseApiServices;

use App\Models\CourseDay;
use Illuminate\Support\Facades\Log;

class CourseDayAttendanceSyncService
{
    public function __construct(
        protected CourseUvsDirectLoadService $directLoadService
    ) {
    }

    public function syncToRemote(CourseDay $day, ?array $onlyLocalPersonIds = null): bool
    {
        Log::warning('CourseDayAttendanceSyncService.syncToRemote: Im Admin ist derzeit nur der direkte Load vorgesehen.', [
            'day_id' => $day->id,
            'course_id' => $day->course_id,
        ]);

        return false;
    }

    public function loadFromRemote(CourseDay $day, ?array $onlyLocalPersonIds = null): bool
    {
        //Log::info('CourseDayAttendanceSyncService.loadFromRemote: Loading attendance data from remote.', [
        //    'day_id' => $day->id,
        //    'course_id' => $day->course_id,
        //]);
        return $this->directLoadService->loadAttendanceForDay($day, $onlyLocalPersonIds);
    }
}
