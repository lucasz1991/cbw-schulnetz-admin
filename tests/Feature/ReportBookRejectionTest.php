<?php

namespace Tests\Feature;

use App\Jobs\ProcessMailJob;
use App\Livewire\Admin\Tasks\AdminTaskDetail;
use App\Models\{AdminTask, Mail, ReportBook, ReportBookEntry, Setting, User};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\{DB, Gate, Http, Notification, Queue, Schema};
use Illuminate\Support\Testing\Fakes\QueueFake;
use Livewire\Livewire;
use Tests\TestCase;

class ReportBookRejectionTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array',
                'session.driver' => 'array',
                'queue.default' => 'sync',
                'mail.default' => 'array',
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Queue::fake();
        Notification::fake();
        Http::preventStrayRequests();
        Gate::define('jobs.view', fn () => true);
        $this->createReviewSchema();
        Setting::setValue('api', 'base_api_url', 'https://base.example.test');
    }

    public function test_partial_rejection_completes_task_and_records_only_selected_days_in_one_message(): void
    {
        $fixture = $this->createReviewFixture();
        $component = $this->openReview($fixture)
            ->set('entryApprovals.'.$fixture['reject']->id, false)
            ->set('rejectionComment', 'Bitte den Ablauf genauer beschreiben.')
            ->assertSee('Begründung zur Ablehnung')
            ->call('rejectReportBook')
            ->assertSet('showDetailModal', false)
            ->assertDispatched('taskCompleted');

        $this->assertSame(3, $fixture['reject']->fresh()->status);
        $this->assertSame(2, $fixture['approve']->fresh()->status);
        $this->assertSame(2, $fixture['reviewed']->fresh()->status);
        $this->assertSame(AdminTask::STATUS_COMPLETED, (int) $fixture['task']->fresh()->status);
        $this->assertNotNull($fixture['task']->fresh()->completed_at);
        $this->assertDatabaseCount('mails', 1);

        $mail = Mail::query()->sole();
        $this->assertSame('both', $mail->type);
        $this->assertSame('Berichtsheft abgelehnt', $mail->content['subject']);
        $this->assertStringContainsString('Bitte den Ablauf genauer beschreiben.', $mail->content['body']);
        $this->assertStringContainsString('20.10.2026', $mail->content['body']);
        $this->assertStringContainsString('/user/reportbook?course='.$fixture['book']->course_id.'&day=101', $mail->content['body']);
        $this->assertStringNotContainsString('21.10.2026', $mail->content['body']);
        $this->assertSame($fixture['participant']->id, $mail->recipients[0]['user_id']);
        Queue::assertPushed(ProcessMailJob::class, 1);
        Http::assertNothingSent();

        // A second click arriving after successful completion must not duplicate the message.
        $component->call('rejectReportBook');
        $this->assertDatabaseCount('mails', 1);
        Queue::assertPushed(ProcessMailJob::class, 1);
    }

    public function test_reopening_completed_task_does_not_reject_again_or_send_another_message(): void
    {
        $fixture = $this->createReviewFixture();
        $this->openReview($fixture)
            ->set('entryApprovals.'.$fixture['reject']->id, false)
            ->set('rejectionComment', 'Bitte ergänzen.')
            ->call('rejectReportBook');

        $this->openReview($fixture)
            ->set('entryApprovals.'.$fixture['reject']->id, false)
            ->set('rejectionComment', 'Veralteter zweiter Klick')
            ->call('rejectReportBook')
            ->assertNotDispatched('taskCompleted');

        $this->assertDatabaseCount('mails', 1);
        $this->assertSame(3, $fixture['reject']->fresh()->status);
        Queue::assertPushed(ProcessMailJob::class, 1);
    }

    public function test_changed_assignment_prevents_entry_and_message_writes(): void
    {
        $fixture = $this->createReviewFixture();
        $component = $this->openReview($fixture)
            ->set('entryApprovals.'.$fixture['reject']->id, false)
            ->set('rejectionComment', 'Nicht mehr zuständig');
        $replacement = User::withoutEvents(fn () => User::create([
            'name' => 'Neue Prüfung', 'email' => 'replacement@example.test', 'role' => 'admin',
        ]));
        DB::table('admin_tasks')->where('id', $fixture['task']->id)->update(['assigned_to' => $replacement->id]);

        $component->call('rejectReportBook')->assertNotDispatched('taskCompleted');

        $this->assertSame(1, $fixture['reject']->fresh()->status);
        $this->assertSame(1, $fixture['approve']->fresh()->status);
        $this->assertSame(AdminTask::STATUS_IN_PROGRESS, (int) $fixture['task']->fresh()->status);
        $this->assertDatabaseCount('mails', 0);
        Queue::assertNotPushed(ProcessMailJob::class);
        Http::assertNothingSent();
    }

    public function test_rejection_requires_a_selected_entry_and_preserves_existing_approvals(): void
    {
        $fixture = $this->createReviewFixture();
        $this->openReview($fixture)
            ->set('entryApprovals.'.$fixture['reviewed']->id, false)
            ->call('rejectReportBook')
            ->assertSet('showDetailModal', true)
            ->assertNotDispatched('taskCompleted');

        $this->assertSame(1, $fixture['reject']->fresh()->status);
        $this->assertSame(1, $fixture['approve']->fresh()->status);
        $this->assertSame(2, $fixture['reviewed']->fresh()->status);
        $this->assertDatabaseCount('mails', 0);
        Queue::assertNotPushed(ProcessMailJob::class);
    }

    public function test_message_failure_rolls_back_entry_updates_and_keeps_task_open(): void
    {
        $fixture = $this->createReviewFixture();
        $component = $this->openReview($fixture)
            ->set('entryApprovals.'.$fixture['reject']->id, false)
            ->set('rejectionComment', 'Begründung bleibt für einen erneuten Versuch erhalten.');
        new Mail();
        $dispatcher = Mail::getEventDispatcher();
        Mail::setEventDispatcher(clone $dispatcher);
        Mail::creating(function () {
            throw new \RuntimeException('Synthetic message persistence failure');
        });

        try {
            $component->call('rejectReportBook')
                ->assertHasErrors('reportBookReview')
                ->assertSet('showDetailModal', true)
                ->assertSet('entryApprovals.'.$fixture['reject']->id, false)
                ->assertSet('rejectionComment', 'Begründung bleibt für einen erneuten Versuch erhalten.')
                ->assertNotDispatched('taskCompleted');
        } finally {
            Mail::setEventDispatcher($dispatcher);
        }

        $this->assertSame(1, $fixture['reject']->fresh()->status);
        $this->assertSame(1, $fixture['approve']->fresh()->status);
        $this->assertSame(2, $fixture['reviewed']->fresh()->status);
        $this->assertSame(AdminTask::STATUS_IN_PROGRESS, (int) $fixture['task']->fresh()->status);
        $this->assertNull($fixture['task']->fresh()->completed_at);
        $this->assertDatabaseCount('mails', 0);
        Queue::assertNotPushed(ProcessMailJob::class);
        Http::assertNothingSent();

        $component->call('rejectReportBook')
            ->assertHasNoErrors('reportBookReview')
            ->assertSet('showDetailModal', false)
            ->assertDispatched('taskCompleted');
        $this->assertSame(3, $fixture['reject']->fresh()->status);
        $this->assertSame(2, $fixture['approve']->fresh()->status);
        $this->assertDatabaseCount('mails', 1);
        Queue::assertPushed(ProcessMailJob::class, 1);
    }

    public function test_queue_failure_after_mail_insert_rolls_back_and_retry_queues_exactly_one_message(): void
    {
        $fixture = $this->createReviewFixture();
        $component = $this->openReview($fixture)
            ->set('entryApprovals.'.$fixture['reject']->id, false)
            ->set('rejectionComment', 'Bitte erneut prüfen.');
        $queue = $this->failMessageQueue();

        $component->call('rejectReportBook')
            ->assertHasErrors('reportBookReview')
            ->assertSee('Deine Auswahl und Begründung bleiben erhalten.')
            ->assertSet('showDetailModal', true)
            ->assertSet('entryApprovals.'.$fixture['reject']->id, false)
            ->assertSet('rejectionComment', 'Bitte erneut prüfen.')
            ->assertNotDispatched('taskCompleted');

        $this->assertTrue($queue->mailWasPersisted);
        $this->assertSame(1, $queue->messageAttempts);
        $this->assertSame(1, $fixture['reject']->fresh()->status);
        $this->assertSame(1, $fixture['approve']->fresh()->status);
        $this->assertSame(AdminTask::STATUS_IN_PROGRESS, (int) $fixture['task']->fresh()->status);
        $this->assertDatabaseCount('mails', 0);
        Queue::assertNotPushed(ProcessMailJob::class);

        $queue->failMessagePush = false;
        $component->call('rejectReportBook')
            ->assertHasNoErrors('reportBookReview')
            ->assertSet('showDetailModal', false)
            ->assertDispatched('taskCompleted');

        $this->assertSame(2, $queue->messageAttempts);
        $this->assertSame(3, $fixture['reject']->fresh()->status);
        $this->assertSame(2, $fixture['approve']->fresh()->status);
        $this->assertDatabaseCount('mails', 1);
        Queue::assertPushed(ProcessMailJob::class, 1);
        Http::assertNothingSent();
    }

    public function test_opening_a_fresh_review_clears_previous_error_and_review_draft(): void
    {
        $fixture = $this->createReviewFixture();
        $component = $this->openReview($fixture)
            ->set('entryApprovals.'.$fixture['reject']->id, false)
            ->set('rejectionComment', 'Vorheriger Entwurf');
        $this->failMessageQueue();

        $component->call('rejectReportBook')->assertHasErrors('reportBookReview');
        $component->call('open', ['taskId' => $fixture['task']->id])
            ->assertHasNoErrors('reportBookReview')
            ->assertSet('showDetailModal', true)
            ->assertSet('viewMode', 'task')
            ->assertSet('entryApprovals.'.$fixture['reject']->id, true)
            ->assertSet('rejectionComment', '')
            ->assertDontSee('Die Prüfung konnte nicht gespeichert werden.');

        $this->assertSame(1, $fixture['reject']->fresh()->status);
        $this->assertDatabaseCount('mails', 0);
        Queue::assertNotPushed(ProcessMailJob::class);
    }

    public function test_approval_uses_same_retryable_error_handling_and_keeps_existing_signature(): void
    {
        $fixture = $this->createReviewFixture();
        $component = $this->openReview($fixture);
        $signatureId = DB::table('files')->insertGetId([
            'fileable_type' => ReportBook::class, 'fileable_id' => $fixture['book']->id,
            'type' => 'sign_reportbook_trainer',
        ]);
        $queue = $this->failMessageQueue();

        $component->call('approveReportBook')
            ->assertHasErrors('reportBookReview')
            ->assertSet('showDetailModal', true)
            ->assertSet('entryApprovals.'.$fixture['approve']->id, true)
            ->assertNotDispatched('taskCompleted');

        $this->assertTrue($queue->mailWasPersisted);
        $this->assertSame(1, $fixture['reject']->fresh()->status);
        $this->assertSame(1, $fixture['approve']->fresh()->status);
        $this->assertSame(AdminTask::STATUS_IN_PROGRESS, (int) $fixture['task']->fresh()->status);
        $this->assertDatabaseHas('files', ['id' => $signatureId]);
        $this->assertDatabaseCount('mails', 0);

        $queue->failMessagePush = false;
        $component->call('approveReportBook')
            ->assertHasNoErrors('reportBookReview')
            ->assertSet('showDetailModal', false)
            ->assertDispatched('taskCompleted');

        $this->assertSame(2, $fixture['reject']->fresh()->status);
        $this->assertSame(2, $fixture['approve']->fresh()->status);
        $this->assertDatabaseCount('mails', 1);
        $this->assertSame('Berichtsheft freigegeben', Mail::query()->sole()->content['subject']);
        Queue::assertPushed(ProcessMailJob::class, 1);
        Http::assertNothingSent();
    }

    protected function failMessageQueue(): QueueFake
    {
        $queue = new class($this->app) extends QueueFake
        {
            public bool $failMessagePush = true;
            public bool $mailWasPersisted = false;
            public int $messageAttempts = 0;

            public function push($job, $data = '', $queue = null)
            {
                if ($job instanceof ProcessMailJob) {
                    $this->messageAttempts++;
                    $this->mailWasPersisted = $job->mail->exists
                        && Mail::query()->whereKey($job->mail->id)->exists();
                    if ($this->failMessagePush) {
                        throw new \RuntimeException('Synthetic queue dispatch failure');
                    }
                }

                return parent::push($job, $data, $queue);
            }
        };
        Queue::swap($queue);

        return $queue;
    }

    protected function openReview(array $fixture)
    {
        return Livewire::actingAs($fixture['reviewer'])->test(AdminTaskDetail::class)
            ->call('open', ['taskId' => $fixture['task']->id])
            ->assertSet('showDetailModal', true)
            ->call('switchToContext')
            ->assertSet('viewMode', 'context')
            ->assertSee('Dokumentation Tag eins');
    }

    /** Synthetic fixture shared with local browser QA; never reads the configured application DB. */
    public function createReviewFixture(): array
    {
        $reviewer = User::withoutEvents(fn () => User::create([
            'name' => 'Testprüfung', 'email' => 'reviewer@example.test', 'role' => 'admin',
        ]));
        $participant = User::withoutEvents(fn () => User::create([
            'name' => 'Testteilnahme', 'email' => 'participant@example.test', 'role' => 'guest',
        ]));
        DB::table('persons')->insert(['user_id' => $participant->id, 'institut_id' => 2]);
        $personId = DB::table('persons')->value('id');
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'Testbaustein', 'klassen_id' => 'TEST-101',
            'planned_start_date' => '2026-10-19', 'planned_end_date' => '2026-10-30',
        ]);
        DB::table('course_participant_enrollments')->insert(['person_id' => $personId, 'course_id' => $courseId]);
        $book = ReportBook::create(['user_id' => $participant->id, 'course_id' => $courseId, 'title' => 'Testheft']);
        $reject = ReportBookEntry::create([
            'report_book_id' => $book->id, 'course_day_id' => 101, 'entry_date' => '2026-10-20',
            'text' => '<p>Dokumentation Tag eins</p>', 'status' => 1,
        ]);
        $approve = ReportBookEntry::create([
            'report_book_id' => $book->id, 'course_day_id' => 102, 'entry_date' => '2026-10-21',
            'text' => '<p>Dokumentation Tag zwei</p>', 'status' => 1,
        ]);
        $reviewed = ReportBookEntry::create([
            'report_book_id' => $book->id, 'course_day_id' => 103, 'entry_date' => '2026-10-22',
            'text' => '<p>Bereits freigegebener Tag</p>', 'status' => 2,
        ]);
        $task = AdminTask::create([
            'created_by' => $participant->id, 'context_type' => ReportBook::class, 'context_id' => $book->id,
            'task_type' => AdminTask::TYPE_REPORTBOOK_REVIEW, 'status' => AdminTask::STATUS_IN_PROGRESS,
            'assigned_to' => $reviewer->id,
        ]);

        return compact('reviewer', 'participant', 'book', 'task', 'reject', 'approve', 'reviewed');
    }

    public function createReviewSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('email'); $table->string('role'); $table->timestamps();
        });
        Schema::create('persons', function (Blueprint $table) {
            $table->id(); $table->integer('user_id'); $table->integer('institut_id');
            $table->json('programdata')->nullable(); $table->json('statusdata')->nullable(); $table->softDeletes();
        });
        Schema::create('courses', function (Blueprint $table) {
            $table->id(); $table->string('title'); $table->string('klassen_id')->nullable();
            $table->date('planned_start_date')->nullable(); $table->date('planned_end_date')->nullable();
            $table->string('type')->nullable(); $table->string('vtz')->nullable(); $table->softDeletes(); $table->timestamps();
        });
        Schema::create('course_participant_enrollments', function (Blueprint $table) {
            $table->id(); $table->integer('person_id'); $table->integer('course_id');
            $table->json('source_snapshot')->nullable(); $table->boolean('is_active')->default(true); $table->softDeletes();
        });
        Schema::create('report_books', function (Blueprint $table) {
            $table->id(); $table->integer('user_id'); $table->integer('course_id');
            $table->string('massnahme_id')->nullable(); $table->string('title'); $table->json('settings')->nullable(); $table->timestamps();
        });
        Schema::create('report_book_entries', function (Blueprint $table) {
            $table->id(); $table->integer('report_book_id'); $table->integer('course_day_id');
            $table->date('entry_date')->nullable(); $table->text('text'); $table->integer('status');
            $table->timestamp('submitted_at')->nullable(); $table->timestamps();
        });
        Schema::create('admin_tasks', function (Blueprint $table) {
            $table->id(); $table->integer('created_by'); $table->string('context_type'); $table->integer('context_id');
            $table->string('task_type'); $table->integer('status'); $table->integer('priority')->default(2);
            $table->text('description')->nullable(); $table->timestamp('due_at')->nullable();
            $table->integer('assigned_to')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
        Schema::create('files', function (Blueprint $table) {
            $table->id(); $table->morphs('fileable'); $table->string('type'); $table->string('path')->nullable(); $table->timestamps();
        });
        Schema::create('settings', function (Blueprint $table) {
            $table->id(); $table->string('type'); $table->string('key'); $table->text('value')->nullable(); $table->timestamps();
        });
        Schema::create('mails', function (Blueprint $table) {
            $table->id(); $table->string('type'); $table->boolean('status');
            $table->json('content'); $table->json('recipients'); $table->timestamps();
        });
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id(); $table->nullableMorphs('causer'); $table->timestamps();
        });
        (require dirname(base_path()).'/base/database/migrations/2026_10_19_180000_create_report_book_location_policies.php')->up();
    }
}
