<?php

namespace Tests\Feature;

use App\Models\AdminTask;
use App\Models\ReportBook;
use App\Services\ReportBooks\AdminTaskVisibility;
use App\Services\ReportBooks\LocationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\{DB, Http, Notification, Queue, Schema};
use Tests\TestCase;

class ReportBookLocationPerformanceTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app): void {
            $app['config']->set([
                'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync',
            ]);
        });
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Queue::fake(); Notification::fake(); Http::preventStrayRequests();
        $this->setNow('2026-10-23 10:00:00 UTC');
        Schema::create('persons', function (Blueprint $t): void {
            $t->id(); $t->integer('user_id')->index(); $t->integer('institut_id');
            $t->json('programdata')->nullable(); $t->json('statusdata')->nullable(); $t->softDeletes();
        });
        Schema::create('course_participant_enrollments', function (Blueprint $t): void {
            $t->id(); $t->integer('person_id')->index(); $t->integer('course_id')->index();
            $t->json('source_snapshot')->nullable(); $t->boolean('is_active')->default(true); $t->softDeletes();
        });
        Schema::create('report_books', function (Blueprint $t): void {
            $t->id(); $t->integer('user_id'); $t->integer('course_id'); $t->string('massnahme_id')->nullable();
            $t->string('title')->nullable(); $t->json('settings')->nullable(); $t->timestamps();
        });
        Schema::create('report_book_entries', function (Blueprint $t): void {
            $t->id(); $t->integer('report_book_id'); $t->integer('status'); $t->timestamps();
        });
        Schema::create('admin_tasks', function (Blueprint $t): void {
            $t->id(); $t->integer('created_by'); $t->string('context_type'); $t->integer('context_id');
            $t->string('task_type'); $t->integer('status'); $t->integer('priority')->default(2);
            $t->text('description')->nullable(); $t->timestamp('due_at')->nullable();
            $t->integer('assigned_to')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamps();
        });
        (require dirname(base_path()).'/base/database/migrations/2026_10_19_180000_create_report_book_location_policies.php')->up();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow(); \Illuminate\Support\Carbon::setTestNow();
        parent::tearDown();
    }

    public static function instituteRuleProvider(): array
    {
        return ['unconfigured default' => [null], 'required' => [true], 'disabled' => [false]];
    }

    /** @dataProvider instituteRuleProvider */
    public function test_counter_query_count_stays_bounded_as_the_open_book_inventory_grows(?bool $required): void
    {
        if ($required !== null) $this->addRevision(1, $required);
        $counts = [];
        foreach ([10, 100, 458] as $size) {
            $this->seedBooks($size);
            [$visible, $queries] = $this->measure(fn () => AdminTaskVisibility::apply(
                AdminTask::open()->where('task_type', AdminTask::TYPE_REPORTBOOK_REVIEW)
            )->count());
            $this->assertSame($required === false ? 0 : $size, $visible);
            $counts[$size] = count($queries);
            $this->assertLessThan(30, $counts[$size], "Counter with {$size} books must stay below 30 SQL queries; got {$counts[$size]}.");
        }
        $this->assertLessThanOrEqual($counts[10] + 16, $counts[458], 'Growing the inventory must not add queries per book.');
    }

    public function test_mixed_institutes_and_location_filters_keep_the_counter_within_budget(): void
    {
        $this->seedBooks(458, [1, 2, 3, 4]);
        $this->addRevision(1, true);
        $this->addRevision(2, false);
        $this->addRevision(4, false);
        [$all, $queries] = $this->measure(fn () => AdminTaskVisibility::apply(AdminTask::open())->count());
        $this->assertSame(229, $all);
        $this->assertLessThan(30, count($queries));
        [$oneSite, $queries] = $this->measure(fn () => AdminTaskVisibility::apply(AdminTask::open(), 3)->count());
        $this->assertSame(114, $oneSite);
        $this->assertLessThan(30, count($queries));
    }

    public static function chunkBoundaryProvider(): array
    {
        return ['exact first block' => [500], 'first row of second block' => [501], 'three blocks' => [1100]];
    }

    /** @dataProvider chunkBoundaryProvider */
    public function test_chunk_boundaries_preserve_visibility_and_reuse_repeated_books(int $size): void
    {
        $this->seedBooks($size, [1, 2]);
        $this->addRevision(1, true);
        $this->addRevision(2, false);
        // Each later block repeats one visible and one hidden book from the first block.
        // The remaining tasks still require fresh contexts, including in the third block.
        foreach ([501 => 1, 502 => 2, 1001 => 1, 1002 => 2] as $taskId => $bookId) {
            if ($taskId <= $size) DB::table('admin_tasks')->where('id', $taskId)->update(['context_id' => $bookId]);
        }
        [[$count, $ids], $queries] = $this->measure(function (): array {
            $query = AdminTaskVisibility::apply(AdminTask::open());
            return [$query->count(), $query->orderBy('id')->pluck('id')->all()];
        });
        $expectedIds = array_values(array_filter(range(1, $size), fn ($id) => $id % 2 === 1));
        $this->assertSame(count($expectedIds), $count);
        $this->assertSame($expectedIds, $ids, 'Visible tasks must remain complete and ordered across every block.');
        $blocks = (int) ceil($size / 500);
        $this->assertLessThanOrEqual(5 + 10 * $blocks, count($queries), 'SQL work must grow per block rather than per book.');
        $contextQueries = array_filter($queries, fn ($query) => str_contains($query['query'], 'course_participant_enrollments'));
        $this->assertLessThanOrEqual($blocks, count($contextQueries), 'At most one enrollment query is allowed per block.');
    }

    public function test_bulk_resolution_preserves_measure_course_ambiguity_and_invalid_json_semantics(): void
    {
        $this->addRevision(1, true);
        $this->addRevision(2, false);
        $this->addBook(1, 10, 1, 'A');
        $this->addBook(2, 10, 1, 'B');
        $this->addBook(3, 10, 2, 'A');
        $this->addPerson(1, 10, 1, 1, ['nested' => ['massnahme_id' => 'A']]);
        $this->addPerson(2, 10, 1, 2, [], [], ['course' => ['massnahmen_id' => 'B']]);
        $this->addPerson(3, 10, 2, 2, ['massnahme_id' => 'A']);
        $this->addBook(4, 20, 1, 'A');
        $this->addPerson(4, 20, 1, 1);
        $this->addPerson(5, 20, 1, 3);
        $this->addBook(5, 30, 1, 'A');
        $this->addPerson(6, 30, 1, 1);
        $this->addPerson(7, 30, 1, 2);
        $this->addBook(6, 40, 1, 'A');
        $this->addPerson(8, 40, 1, 0);
        $this->addBook(7, 50, 1, 'A');
        $this->addPerson(9, 50, 1, 1, ['massnahme_id' => 'A']);
        $this->addPerson(10, 50, 1, 2, [], ['massnahme_id' => 'B']);
        DB::table('persons')->where('id', 10)->update(['programdata' => '{invalid']);
        $this->addBook(8, 60, 1, 'A'); // No enrollment: unknown context.
        $this->addBook(9, 70, 1, 'A');
        $this->addPerson(11, 70, 1, 2);
        DB::table('persons')->where('id', 11)->update(['deleted_at' => '2026-01-01 00:00:00']);
        DB::table('course_participant_enrollments')->where('person_id', 11)->update([
            'is_active' => false, 'deleted_at' => '2026-01-01 00:00:00',
        ]);
        $this->addBook(10, 80, 1, null);
        $this->addBook(11, 80, 1, '');
        $this->addBook(12, 80, 1, 'A');
        $this->addPerson(12, 80, 1, 1);
        DB::table('persons')->where('id', 12)->update(['programdata' => 'null']);

        $books = ReportBook::orderBy('id')->get();
        [$contexts, $queries] = $this->measure(fn () => LocationPolicy::forBooks($books));
        $this->assertLessThan(15, count($queries));
        foreach ($books as $book) {
            $this->assertSame(LocationPolicy::forBook($book), $contexts[$book->id], 'Batch differs from existing resolver for book '.$book->id);
        }
        $this->assertSame('normal', $contexts[1]['mode']);
        $this->assertSame([1], $contexts[1]['institute_ids']);
        $this->assertSame('archive', $contexts[2]['mode']);
        $this->assertSame('archive', $contexts[3]['mode']);
        $this->assertSame('normal', $contexts[4]['mode']);
        $this->assertTrue($contexts[4]['ambiguous']);
        foreach ([5, 6, 7, 8] as $id) $this->assertSame('conflict', $contexts[$id]['mode']);
        $this->assertSame('archive', $contexts[9]['mode'], 'Historical books retain inactive/deleted enrollment resolution.');
        $this->assertSame('normal', $contexts[10]['mode']);
        $this->assertSame('normal', $contexts[11]['mode']);
        $this->assertSame('conflict', $contexts[12]['mode']);
    }

    public function test_effective_boundary_cancellation_and_context_changes_are_not_cached_across_calls(): void
    {
        $this->seedBooks(10);
        $books = ReportBook::orderBy('id')->get();
        $this->addRevision(1, true);
        $future = $this->addRevision(1, false, '2026-10-23 10:01:00');
        $this->assertSame('normal', LocationPolicy::forBooks($books)[1]['mode']);
        $this->assertSame('archive', LocationPolicy::forBooks($books, CarbonImmutable::parse('2026-10-23 12:01:00 Europe/Berlin'))[1]['mode']);
        $this->assertSame(10, AdminTaskVisibility::apply(AdminTask::open())->count());
        $this->setNow('2026-10-23 10:01:00 UTC');
        $this->assertSame(0, AdminTaskVisibility::apply(AdminTask::open())->count());
        DB::table('report_book_policy_audit')->insert([
            'revision_id' => $future, 'event_key' => 'synthetic:cancel:'.$future,
            'event' => 'revision_cancelled', 'payload' => '{}', 'created_at' => '2026-10-23 10:01:00',
        ]);
        $this->assertSame(10, AdminTaskVisibility::apply(AdminTask::open())->count());
        $this->addRevision(1, false, '2026-10-23 10:01:00');
        $this->assertSame(0, AdminTaskVisibility::apply(AdminTask::open())->count());
        DB::table('persons')->where('id', 1)->update(['institut_id' => 3]);
        $this->assertSame(1, AdminTaskVisibility::apply(AdminTask::open())->count());
        $this->assertSame([3], LocationPolicy::forBooks($books)[1]['institute_ids']);
    }

    public function test_missing_schema_default_is_not_reused_after_the_schema_is_created(): void
    {
        $this->seedBooks(10);
        Schema::drop('report_book_institute_policies');
        [$count, $queries] = $this->measure(fn () => AdminTaskVisibility::apply(AdminTask::open())->count());
        $this->assertSame(10, $count);
        $this->assertLessThan(10, count($queries));
        Schema::create('report_book_institute_policies', function (Blueprint $t): void {
            $t->id(); $t->unsignedInteger('institut_id')->unique(); $t->unsignedInteger('version')->default(0); $t->timestamps();
        });
        $this->addRevision(1, false);
        $this->assertSame(0, AdminTaskVisibility::apply(AdminTask::open())->count());
    }

    public function test_bulk_resolution_in_a_transaction_preserves_the_current_read_contract(): void
    {
        $this->seedBooks(2);
        $this->addRevision(1, true);
        $books = ReportBook::orderBy('id')->get();
        DB::transaction(function () use ($books): void {
            $this->assertSame('normal', LocationPolicy::forBooks($books)[1]['mode']);
            $this->addRevision(1, false);
            $contexts = LocationPolicy::forBooks($books);
            $this->assertSame('archive', $contexts[1]['mode']);
            foreach ($books as $book) $this->assertSame(LocationPolicy::forBook($book, null, true), $contexts[$book->id]);
        });
    }

    public function test_empty_bulk_resolution_does_not_query_the_database(): void
    {
        [$contexts, $queries] = $this->measure(fn () => LocationPolicy::forBooks([]));
        $this->assertSame([], $contexts);
        $this->assertSame([], $queries);
    }

    private function seedBooks(int $size, array $institutes = [1]): void
    {
        foreach (['admin_tasks', 'report_books', 'course_participant_enrollments', 'persons'] as $table) DB::table($table)->delete();
        for ($id = 1; $id <= $size; $id++) {
            $this->addBook($id, $id, 1, 'A');
            $this->addPerson($id, $id, 1, $institutes[($id - 1) % count($institutes)]);
            DB::table('admin_tasks')->insert([
                'id' => $id, 'created_by' => 1, 'context_type' => ReportBook::class, 'context_id' => $id,
                'task_type' => AdminTask::TYPE_REPORTBOOK_REVIEW, 'status' => AdminTask::STATUS_OPEN,
            ]);
        }
    }

    private function addBook(int $id, int $userId, int $courseId, ?string $measure): void
    {
        DB::table('report_books')->insert(['id' => $id, 'user_id' => $userId, 'course_id' => $courseId, 'massnahme_id' => $measure]);
    }

    private function addPerson(int $id, int $userId, int $courseId, int $instituteId, array $program = [], array $status = [], array $source = []): void
    {
        DB::table('persons')->insert(['id' => $id, 'user_id' => $userId, 'institut_id' => $instituteId,
            'programdata' => json_encode($program), 'statusdata' => json_encode($status)]);
        DB::table('course_participant_enrollments')->insert(['id' => $id, 'person_id' => $id, 'course_id' => $courseId, 'source_snapshot' => json_encode($source)]);
    }

    private function addRevision(int $instituteId, bool $required, string $effectiveAt = '2026-10-23 09:00:00'): int
    {
        $policyId = DB::table('report_book_institute_policies')->where('institut_id', $instituteId)->value('id');
        if (! $policyId) $policyId = DB::table('report_book_institute_policies')->insertGetId(['institut_id' => $instituteId]);
        $number = (int) DB::table('report_book_policy_revisions')->where('policy_id', $policyId)->max('revision') + 1;
        return DB::table('report_book_policy_revisions')->insertGetId([
            'policy_id' => $policyId, 'revision' => $number, 'required' => $required, 'stamp_enabled' => false,
            'effective_at' => $effectiveAt, 'created_by' => 1, 'created_at' => '2026-10-23 09:00:00',
        ]);
    }

    private function measure(callable $callback): array
    {
        DB::flushQueryLog(); DB::enableQueryLog();
        try { $result = $callback(); $queries = DB::getQueryLog(); }
        finally { DB::disableQueryLog(); DB::flushQueryLog(); }
        return [$result, $queries];
    }

    private function setNow(string $instant): void
    {
        CarbonImmutable::setTestNow($instant); \Illuminate\Support\Carbon::setTestNow($instant);
    }
}
