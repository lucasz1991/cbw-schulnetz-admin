<?php

namespace App\Services\ReportBooks;

use App\Models\AdminTask;
use App\Models\ReportBook;
use Illuminate\Database\Eloquent\Builder;

class AdminTaskVisibility
{
    /** Apply before pagination, limits or counts on every task surface. */
    public static function apply(Builder $query, ?int $institution = null): Builder
    {
        $excluded = [];
        $contexts = [];
        $candidates = (clone $query)->reorder()->setEagerLoads([])->with('context')
            ->where('task_type', AdminTask::TYPE_REPORTBOOK_REVIEW);
        if ($institution === null) {
            $candidates->whereIn('status', [AdminTask::STATUS_OPEN, AdminTask::STATUS_IN_PROGRESS]);
        }
        $candidates->chunkById(200, function ($tasks) use (&$excluded, &$contexts, $institution): void {
            foreach ($tasks as $task) {
                $book = $task->context;
                $context = $book instanceof ReportBook
                    ? ($contexts[$book->id] ??= LocationPolicy::forBook($book))
                    : ['mode' => LocationPolicy::schemaReady() ? 'conflict' : 'normal', 'institute_ids' => []];
                if ((in_array((int) $task->status, [AdminTask::STATUS_OPEN, AdminTask::STATUS_IN_PROGRESS], true)
                        && $context['mode'] !== 'normal')
                    || ($institution !== null && ! in_array($institution, $context['institute_ids'], true))) {
                    $excluded[] = (int) $task->id;
                }
            }
        });

        return $query->whereNotIn('id', $excluded);
    }
}
