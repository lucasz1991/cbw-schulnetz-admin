<?php

namespace App\Actions\Courses;

use App\Models\AdminTask;
use App\Models\Course;
use App\Models\CourseDay;
use App\Models\File;
use App\Models\ReportBook;
use App\Models\ReportBookEntry;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransferDeletedCourseReportBooks
{
    private const SIGNATURE_FILE_TYPES = [
        'sign_reportbook_participant',
        'sign_reportbook_trainer',
    ];

    public function handle(Course $sourceCourse, Course $targetCourse): array
    {
        if ((int) $sourceCourse->id === (int) $targetCourse->id) {
            throw new \InvalidArgumentException('Quell- und Zielkurs duerfen nicht identisch sein.');
        }

        return DB::transaction(function () use ($sourceCourse, $targetCourse): array {
            $sourceBooks = ReportBook::query()
                ->with([
                    'entries' => fn ($query) => $query->orderBy('entry_date')->orderByDesc('updated_at')->orderByDesc('id'),
                    'files' => fn ($query) => $query->orderByDesc('updated_at')->orderByDesc('id'),
                ])
                ->where('course_id', $sourceCourse->id)
                ->get();

            $summary = $this->makeEmptySummary($sourceBooks->count());

            if ($sourceBooks->isEmpty()) {
                return $summary;
            }

            $targetParticipantUserIds = $this->loadTargetParticipantUserIds($targetCourse);
            $targetDaysByDate = $this->loadTargetDaysByDate($targetCourse);

            $sourceUserIds = $sourceBooks
                ->pluck('user_id')
                ->filter()
                ->map(fn ($userId) => (int) $userId)
                ->unique()
                ->values();

            $targetBooksByUser = ReportBook::query()
                ->with([
                    'entries' => fn ($query) => $query->orderBy('entry_date')->orderByDesc('updated_at')->orderByDesc('id'),
                    'files' => fn ($query) => $query->orderByDesc('updated_at')->orderByDesc('id'),
                ])
                ->where('course_id', $targetCourse->id)
                ->when(
                    $sourceUserIds->isNotEmpty(),
                    fn ($query) => $query->whereIn('user_id', $sourceUserIds->all()),
                    fn ($query) => $query->whereRaw('1 = 0')
                )
                ->get()
                ->groupBy('user_id');

            foreach ($sourceBooks->groupBy('user_id') as $userId => $userSourceBooks) {
                $userId = (int) $userId;

                if (! $targetParticipantUserIds->contains($userId)) {
                    $summary['participants_skipped']++;
                    $summary['books_skipped'] += $userSourceBooks->count();
                    continue;
                }

                $transferableSourceEntries = $this->collectTransferableSourceEntries($userSourceBooks, $targetDaysByDate);
                $summary['entries_ignored_without_matching_day'] += $this->countIgnoredSourceEntries(
                    $userSourceBooks,
                    $transferableSourceEntries
                );

                if ($transferableSourceEntries->isEmpty()) {
                    $summary['participants_without_matching_days']++;
                    continue;
                }

                $summary['participants_processed']++;

                $targetBooks = $targetBooksByUser->get($userId, collect())
                    ->unique('id')
                    ->values();
                $sourceBooksWithTransferableEntries = $userSourceBooks
                    ->filter(fn (ReportBook $book) => $transferableSourceEntries->contains('report_book_id', $book->id))
                    ->values();
                $canonicalBook = $this->resolveCanonicalBook(
                    $targetBooks,
                    $sourceBooksWithTransferableEntries,
                    $targetCourse,
                    $userId
                );

                $this->syncCanonicalBook(
                    $canonicalBook,
                    $targetBooks->concat($sourceBooksWithTransferableEntries)->unique('id')->values(),
                    $targetCourse
                );

                $duplicateBooks = $targetBooks
                    ->reject(fn (ReportBook $book) => (int) $book->id === (int) $canonicalBook->id)
                    ->values();

                $entryResult = $this->mergeEntries(
                    $targetBooks,
                    $transferableSourceEntries,
                    $canonicalBook,
                    $targetDaysByDate
                );

                $summary['entries_kept'] += $entryResult['entries_kept'];
                $summary['entries_deleted'] += $entryResult['entries_deleted'];
                $summary['duplicate_dates_resolved'] += $entryResult['duplicate_dates_resolved'];
                $summary['entries_reassigned'] += $entryResult['entries_reassigned'];
                $summary['entries_relinked_to_day'] += $entryResult['entries_relinked_to_day'];

                $fileResult = $this->mergeFiles($targetBooks, $canonicalBook);

                $summary['files_reassigned'] += $fileResult['files_reassigned'];
                $summary['files_deleted'] += $fileResult['files_deleted'];

                $summary['tasks_relinked'] += $this->relinkReviewTasks(
                    $targetBooks->concat([$canonicalBook])->unique('id')->values(),
                    $canonicalBook
                );

                foreach ($duplicateBooks as $duplicateBook) {
                    $duplicateBook->delete();
                }

                $summary['books_merged'] += $duplicateBooks->count();
                $summary['books_in_target']++;
            }

            return $summary;
        });
    }

    protected function makeEmptySummary(int $sourceBooksCount): array
    {
        return [
            'source_books_found' => $sourceBooksCount,
            'participants_processed' => 0,
            'participants_skipped' => 0,
            'books_in_target' => 0,
            'books_merged' => 0,
            'books_skipped' => 0,
            'entries_kept' => 0,
            'entries_deleted' => 0,
            'duplicate_dates_resolved' => 0,
            'entries_reassigned' => 0,
            'entries_relinked_to_day' => 0,
            'entries_ignored_without_matching_day' => 0,
            'files_reassigned' => 0,
            'files_deleted' => 0,
            'tasks_relinked' => 0,
            'participants_without_matching_days' => 0,
        ];
    }

    protected function loadTargetParticipantUserIds(Course $targetCourse): Collection
    {
        return $targetCourse->participants()
            ->whereNotNull('persons.user_id')
            ->pluck('persons.user_id')
            ->map(fn ($userId) => (int) $userId)
            ->filter()
            ->unique()
            ->values();
    }

    protected function loadTargetDaysByDate(Course $targetCourse): Collection
    {
        return CourseDay::query()
            ->where('course_id', $targetCourse->id)
            ->get()
            ->keyBy(fn (CourseDay $day) => $this->normalizeDate($day->date));
    }

    protected function resolveCanonicalBook(
        Collection $targetBooks,
        Collection $sourceBooks,
        Course $targetCourse,
        int $userId
    ): ReportBook
    {
        $existingTargetBook = $this->sortByFreshnessDescending(
            $targetBooks->filter(fn (ReportBook $book) => (int) $book->course_id === (int) $targetCourse->id)
        )->first();

        if ($existingTargetBook) {
            return $existingTargetBook;
        }

        $sourceBook = $this->sortByFreshnessDescending($sourceBooks)->first();

        if (! $sourceBook) {
            throw new \RuntimeException('Es konnte kein kanonisches Berichtsheft ermittelt werden.');
        }

        $canonicalBook = new ReportBook();
        $canonicalBook->forceFill([
            'user_id' => $userId,
            'course_id' => $targetCourse->id,
            'massnahme_id' => filled($sourceBook->massnahme_id) ? (string) $sourceBook->massnahme_id : null,
            'title' => $sourceBook->title ?: 'Mein Berichtsheft',
            'description' => $sourceBook->description,
            'start_date' => $this->normalizeDate($sourceBook->start_date),
            'end_date' => $this->normalizeDate($sourceBook->end_date),
        ]);

        $settings = $sourceBook->getAttribute('settings');
        if (is_array($settings)) {
            $canonicalBook->setAttribute('settings', $settings);
        }

        $canonicalBook->save();

        return $canonicalBook;
    }

    protected function syncCanonicalBook(ReportBook $canonicalBook, Collection $candidateBooks, Course $targetCourse): void
    {
        $sortedBooks = $this->sortByFreshnessDescending($candidateBooks);
        $freshestBook = $sortedBooks->first();

        $settings = $sortedBooks
            ->map(function (ReportBook $book) {
                $settings = $book->getAttribute('settings');

                if (is_array($settings)) {
                    return $settings;
                }

                if (is_string($settings) && $settings !== '') {
                    $decoded = json_decode($settings, true);

                    return is_array($decoded) ? $decoded : null;
                }

                return null;
            })
            ->first(fn ($value) => is_array($value) && $value !== []);

        $startDate = $candidateBooks
            ->pluck('start_date')
            ->filter()
            ->map(fn ($value) => $this->normalizeDate($value))
            ->sort()
            ->first();

        $endDate = $candidateBooks
            ->pluck('end_date')
            ->filter()
            ->map(fn ($value) => $this->normalizeDate($value))
            ->sort()
            ->last();

        $massnahmeId = $this->resolveMassnahmeId($canonicalBook, $sortedBooks);

        $canonicalBook->forceFill([
            'course_id' => $targetCourse->id,
            'massnahme_id' => $massnahmeId,
            'title' => $this->firstFilledValue($sortedBooks->pluck('title')) ?? 'Mein Berichtsheft',
            'description' => $this->firstFilledValue($sortedBooks->pluck('description')),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        if ($freshestBook && is_array($settings)) {
            $canonicalBook->setAttribute('settings', $settings);
        }

        $canonicalBook->save();
    }

    protected function resolveMassnahmeId(ReportBook $canonicalBook, Collection $sortedBooks): ?string
    {
        if ((int) $canonicalBook->course_id > 0 && $canonicalBook->massnahme_id) {
            return (string) $canonicalBook->massnahme_id;
        }

        $massnahmeId = $sortedBooks
            ->pluck('massnahme_id')
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->first(fn ($value) => filled($value));

        return $massnahmeId ? (string) $massnahmeId : null;
    }

    protected function mergeEntries(
        Collection $targetBooks,
        Collection $transferableSourceEntries,
        ReportBook $canonicalBook,
        Collection &$targetDaysByDate
    ): array {
        $result = [
            'entries_kept' => 0,
            'entries_deleted' => 0,
            'duplicate_dates_resolved' => 0,
            'entries_reassigned' => 0,
            'entries_relinked_to_day' => 0,
        ];

        $entriesByDate = $targetBooks
            ->flatMap(fn (ReportBook $book) => $book->entries)
            ->concat($transferableSourceEntries)
            ->filter(fn (ReportBookEntry $entry) => filled($entry->entry_date))
            ->groupBy(fn (ReportBookEntry $entry) => $this->normalizeDate($entry->entry_date));

        foreach ($entriesByDate as $date => $entriesForDate) {
            if (! $date) {
                continue;
            }

            $sortedEntries = $this->sortByFreshnessDescending($entriesForDate);
            /** @var ReportBookEntry $winner */
            $winner = $sortedEntries->first();

            if (! $winner) {
                continue;
            }

            $losers = $sortedEntries->slice(1);

            if ($losers->isNotEmpty()) {
                $result['duplicate_dates_resolved']++;
            }

            foreach ($losers as $loser) {
                $loser->delete();
                $result['entries_deleted']++;
            }

            $targetDay = $targetDaysByDate->get($date);
            $originalReportBookId = (int) $winner->report_book_id;
            $originalCourseDayId = (int) ($winner->course_day_id ?? 0);
            $targetCourseDayId = $targetDay ? (int) $targetDay->id : ($originalCourseDayId ?: null);

            $winner->forceFill([
                'report_book_id' => $canonicalBook->id,
                'course_day_id' => $targetCourseDayId,
                'entry_date' => $date,
            ]);
            $winner->save();

            if ($originalReportBookId !== (int) $canonicalBook->id) {
                $result['entries_reassigned']++;
            }

            if ($targetDay && $originalCourseDayId !== $targetCourseDayId) {
                $result['entries_relinked_to_day']++;
            }

            $result['entries_kept']++;
        }

        return $result;
    }

    protected function collectTransferableSourceEntries(Collection $sourceBooks, Collection $targetDaysByDate): Collection
    {
        if ($targetDaysByDate->isEmpty()) {
            return collect();
        }

        $targetDates = $targetDaysByDate->keys()->filter()->values()->all();

        return $sourceBooks
            ->flatMap(fn (ReportBook $book) => $book->entries)
            ->filter(fn (ReportBookEntry $entry) => in_array($this->normalizeDate($entry->entry_date), $targetDates, true))
            ->values();
    }

    protected function countIgnoredSourceEntries(Collection $sourceBooks, Collection $transferableSourceEntries): int
    {
        $totalSourceEntries = $sourceBooks
            ->flatMap(fn (ReportBook $book) => $book->entries)
            ->filter(fn (ReportBookEntry $entry) => filled($entry->entry_date))
            ->count();

        return max(0, $totalSourceEntries - $transferableSourceEntries->count());
    }

    protected function mergeFiles(Collection $candidateBooks, ReportBook $canonicalBook): array
    {
        $result = [
            'files_reassigned' => 0,
            'files_deleted' => 0,
        ];

        /** @var \Illuminate\Support\Collection<int, File> $allFiles */
        $allFiles = $candidateBooks
            ->flatMap(fn (ReportBook $book) => $book->files)
            ->filter(fn ($file) => $file instanceof File)
            ->values();

        if ($allFiles->isEmpty()) {
            return $result;
        }

        $signatureFiles = $allFiles
            ->filter(fn (File $file) => in_array($file->type, self::SIGNATURE_FILE_TYPES, true))
            ->groupBy('type');

        foreach ($signatureFiles as $type => $files) {
            $sortedFiles = $this->sortByFreshnessDescending($files);
            /** @var File $winner */
            $winner = $sortedFiles->first();

            if (! $winner) {
                continue;
            }

            foreach ($sortedFiles->slice(1) as $loser) {
                $loser->delete();
                $result['files_deleted']++;
            }

            if ((int) $winner->fileable_id !== (int) $canonicalBook->id || $winner->fileable_type !== ReportBook::class) {
                $winner->forceFill([
                    'fileable_id' => $canonicalBook->id,
                    'fileable_type' => ReportBook::class,
                ]);
                $winner->save();
                $result['files_reassigned']++;
            }
        }

        $otherFiles = $allFiles
            ->reject(fn (File $file) => in_array($file->type, self::SIGNATURE_FILE_TYPES, true))
            ->values();

        foreach ($otherFiles as $file) {
            if ((int) $file->fileable_id === (int) $canonicalBook->id && $file->fileable_type === ReportBook::class) {
                continue;
            }

            $file->forceFill([
                'fileable_id' => $canonicalBook->id,
                'fileable_type' => ReportBook::class,
            ]);
            $file->save();
            $result['files_reassigned']++;
        }

        return $result;
    }

    protected function relinkReviewTasks(Collection $candidateBooks, ReportBook $canonicalBook): int
    {
        $bookIds = $candidateBooks
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($bookIds->isEmpty()) {
            return 0;
        }

        $tasks = AdminTask::query()
            ->where('task_type', 'reportbook_review')
            ->where('context_type', ReportBook::class)
            ->whereIn('context_id', $bookIds->all())
            ->get();

        if (! $this->shouldHaveReviewTask($canonicalBook)) {
            $deletedTasks = 0;

            foreach ($tasks as $task) {
                $task->delete();
                $deletedTasks++;
            }

            return $deletedTasks;
        }

        if ($tasks->isEmpty()) {
            AdminTask::create([
                'created_by' => $canonicalBook->user_id,
                'context_type' => ReportBook::class,
                'context_id' => $canonicalBook->id,
                'task_type' => 'reportbook_review',
                'description' => "Baustein Berichtsheft {$canonicalBook->id} vollstaendig eingereicht - Pruefung & Freigabe erforderlich.",
                'status' => AdminTask::STATUS_OPEN,
                'assigned_to' => null,
                'completed_at' => null,
            ]);

            return 1;
        }

        $canonicalTask = $tasks
            ->sortByDesc(fn (AdminTask $task) => sprintf(
                '%01d%s',
                (int) $task->status === (int) AdminTask::STATUS_IN_PROGRESS ? 1 : 0,
                $this->freshnessKey($task)
            ))
            ->first();

        if (! $canonicalTask) {
            return 0;
        }

        $canonicalTask->forceFill([
            'context_id' => $canonicalBook->id,
            'description' => "Baustein Berichtsheft {$canonicalBook->id} vollstaendig eingereicht - Pruefung & Freigabe erforderlich.",
        ]);

        if ((int) $canonicalTask->status !== (int) AdminTask::STATUS_IN_PROGRESS) {
            $canonicalTask->status = AdminTask::STATUS_OPEN;
            $canonicalTask->assigned_to = null;
            $canonicalTask->completed_at = null;
        }

        $canonicalTask->save();

        $relinked = 0;

        foreach ($tasks as $task) {
            if ((int) $task->id === (int) $canonicalTask->id) {
                continue;
            }

            $task->delete();
            $relinked++;
        }

        return $relinked;
    }

    protected function shouldHaveReviewTask(ReportBook $canonicalBook): bool
    {
        $entries = ReportBookEntry::query()
            ->where('report_book_id', $canonicalBook->id)
            ->get(['entry_date', 'status']);

        if ($entries->isEmpty()) {
            return false;
        }

        if ($entries->contains(fn (ReportBookEntry $entry) => (int) $entry->status < 1)) {
            return false;
        }

        if (! $entries->contains(fn (ReportBookEntry $entry) => (int) $entry->status === 1)) {
            return false;
        }

        $expectedDays = CourseDay::query()
            ->where('course_id', $canonicalBook->course_id)
            ->pluck('date')
            ->map(fn ($date) => $this->normalizeDate($date))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingDays = $entries
            ->pluck('entry_date')
            ->map(fn ($date) => $this->normalizeDate($date))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $expectedDays !== [] && array_diff($expectedDays, $existingDays) === [];
    }

    protected function firstFilledValue(Collection $values): mixed
    {
        return $values->first(function ($value) {
            if (is_string($value)) {
                return trim($value) !== '';
            }

            return filled($value);
        });
    }

    protected function sortByFreshnessDescending(Collection $items): Collection
    {
        return $items
            ->sortByDesc(fn ($item) => $this->freshnessKey($item))
            ->values();
    }

    protected function freshnessKey(Model $model): string
    {
        $updatedAt = $model->updated_at instanceof CarbonInterface
            ? $model->updated_at->timestamp
            : (filled($model->updated_at) ? strtotime((string) $model->updated_at) : 0);

        $createdAt = $model->created_at instanceof CarbonInterface
            ? $model->created_at->timestamp
            : (filled($model->created_at) ? strtotime((string) $model->created_at) : 0);

        return sprintf('%010d%010d%010d', $updatedAt ?: 0, $createdAt ?: 0, (int) $model->id);
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if (! filled($value)) {
            return null;
        }

        return Carbon::parse((string) $value)->toDateString();
    }
}
