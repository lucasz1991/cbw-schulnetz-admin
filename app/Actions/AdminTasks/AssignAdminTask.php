<?php

namespace App\Actions\AdminTasks;

use App\Models\AdminTask;
use App\Models\ReportBook;
use App\Services\ReportBooks\LocationPolicy;
use Illuminate\Support\Facades\DB;

class AssignAdminTask
{
    public function claim(int $taskId, int $userId): ?AdminTask
    {
        return DB::transaction(function () use ($taskId, $userId): ?AdminTask {
            if (! $this->lockWritableContext($taskId)) return null;
            $task = AdminTask::query()
                ->lockForUpdate()
                ->findOrFail($taskId);

            if (! $task->assignTo($userId)) {
                return null;
            }

            return $task;
        }, 3);
    }

    public function takeOver(int $taskId, int $userId, int $expectedAssigneeId): ?AdminTask
    {
        return DB::transaction(function () use ($taskId, $userId, $expectedAssigneeId): ?AdminTask {
            if (! $this->lockWritableContext($taskId)) return null;
            $task = AdminTask::query()
                ->lockForUpdate()
                ->findOrFail($taskId);

            $currentAssigneeId = $task->assigned_to === null
                ? null
                : (int) $task->assigned_to;

            if ($currentAssigneeId !== $expectedAssigneeId) {
                return null;
            }

            if (! $task->takeOverBy($userId)) {
                return null;
            }

            return $task;
        }, 3);
    }

    public function complete(int $taskId, int $userId, int $expectedAssigneeId): ?AdminTask
    {
        return DB::transaction(function () use ($taskId, $userId, $expectedAssigneeId): ?AdminTask {
            if (! $this->lockWritableContext($taskId)) return null;
            $task = AdminTask::query()
                ->lockForUpdate()
                ->findOrFail($taskId);

            if (
                (int) $task->assigned_to !== $expectedAssigneeId
                || $expectedAssigneeId !== $userId
                || (int) $task->status === AdminTask::STATUS_COMPLETED
            ) {
                return null;
            }

            $task->complete();

            return $task;
        }, 3);
    }

    public function release(
        int $taskId,
        int $userId,
        int $expectedAssigneeId,
        bool $canReleaseAny
    ): ?AdminTask {
        return DB::transaction(function () use ($taskId, $userId, $expectedAssigneeId, $canReleaseAny): ?AdminTask {
            if (! $this->lockWritableContext($taskId)) return null;
            $task = AdminTask::query()
                ->lockForUpdate()
                ->findOrFail($taskId);

            if (
                (int) $task->assigned_to !== $expectedAssigneeId
                || (int) $task->status !== AdminTask::STATUS_IN_PROGRESS
                || (! $canReleaseAny && $expectedAssigneeId !== $userId)
            ) {
                return null;
            }

            $task->release();

            return $task;
        }, 3);
    }

    private function lockWritableContext(int $taskId): bool
    {
        $task = AdminTask::query()->findOrFail($taskId);
        if ($task->task_type !== AdminTask::TYPE_REPORTBOOK_REVIEW || $task->context_type !== ReportBook::class) {
            return true;
        }
        $book = ReportBook::query()->find($task->context_id);
        if (! $book) return false;
        LocationPolicy::lockForBook($book);
        $book = ReportBook::query()->lockForUpdate()->find($book->id);

        return $book && LocationPolicy::canWriteBook($book);
    }
}
