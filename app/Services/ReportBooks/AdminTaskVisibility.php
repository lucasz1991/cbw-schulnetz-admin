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
        $missingContext = null;
        $bookType = (new ReportBook)->getMorphClass();
        $candidates = (clone $query)->reorder()->setEagerLoads([])
            ->select(['admin_tasks.id', 'admin_tasks.context_type', 'admin_tasks.context_id', 'admin_tasks.status'])
            ->where('task_type', AdminTask::TYPE_REPORTBOOK_REVIEW);
        if ($institution === null) {
            $candidates->whereIn('status', [AdminTask::STATUS_OPEN, AdminTask::STATUS_IN_PROGRESS]);
        }
        $candidates->chunkById(500, function ($tasks) use (&$excluded, &$contexts, &$missingContext, $institution, $bookType): void {
            $bookIds = $tasks->filter(fn ($task) => in_array($task->context_type, [$bookType, ReportBook::class], true))
                ->pluck('context_id')->unique()->reject(fn ($id) => isset($contexts[$id]))->values();
            if ($bookIds->isNotEmpty()) {
                $books = ReportBook::whereKey($bookIds)->get(['id', 'user_id', 'course_id', 'massnahme_id']);
                $contexts += LocationPolicy::forBooks($books);
            }
            foreach ($tasks as $task) {
                $context = in_array($task->context_type, [$bookType, ReportBook::class], true) && isset($contexts[$task->context_id])
                    ? $contexts[$task->context_id]
                    : ($missingContext ??= ['mode' => LocationPolicy::schemaReady() ? 'conflict' : 'normal', 'institute_ids' => []]);
                if ((in_array((int) $task->status, [AdminTask::STATUS_OPEN, AdminTask::STATUS_IN_PROGRESS], true)
                        && $context['mode'] !== 'normal')
                    || ($institution !== null && ! in_array($institution, $context['institute_ids'], true))) {
                    $excluded[] = (int) $task->id;
                }
            }
        });

        return $query->whereIntegerNotInRaw('admin_tasks.id', $excluded);
    }
}
