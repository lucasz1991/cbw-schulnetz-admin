<?php

namespace App\Actions\AdminTasks;

use App\Models\AdminTask;
use App\Models\ReportBook;
use App\Services\ReportBooks\LocationPolicy;
use Closure;
use Illuminate\Support\Facades\DB;

class CompleteReportBookReview
{
    public function handle(
        int $taskId,
        int $userId,
        int $expectedReportBookId,
        Closure $review
    ): ?AdminTask {
        return DB::transaction(function () use ($taskId, $userId, $expectedReportBookId, $review): ?AdminTask {
            $reportBook = ReportBook::query()->find($expectedReportBookId);
            if (! $reportBook) {
                return null;
            }
            LocationPolicy::lockForBook($reportBook);
            $reportBook = ReportBook::query()->lockForUpdate()->find($expectedReportBookId);
            if (! $reportBook || ! LocationPolicy::canWriteBook($reportBook)) {
                return null;
            }
            $task = AdminTask::query()
                ->lockForUpdate()
                ->find($taskId);

            if (
                ! $task
                || $task->task_type !== AdminTask::TYPE_REPORTBOOK_REVIEW
                || (int) $task->status !== AdminTask::STATUS_IN_PROGRESS
                || (int) $task->assigned_to !== $userId
                || $task->context_type !== ReportBook::class
                || (int) $task->context_id !== $expectedReportBookId
            ) {
                return null;
            }

            if (LocationPolicy::schemaReady()) {
                $reportBook->setRelation('entries', $reportBook->entries()->orderBy('id')->lockForUpdate()->get());
            }
            $review($reportBook);
            $task->complete();

            return $task->fresh(['creator', 'assignedAdmin', 'context']);
        }, 3);
    }
}
