<?php

namespace App\Actions\AdminTasks;

use App\Models\AdminTask;
use Illuminate\Support\Facades\DB;

class AssignAdminTask
{
    public function claim(int $taskId, int $userId): ?AdminTask
    {
        return DB::transaction(function () use ($taskId, $userId): ?AdminTask {
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
}
