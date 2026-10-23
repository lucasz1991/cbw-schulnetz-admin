<?php

namespace Tests\Feature;

use App\Actions\AdminTasks\AssignAdminTask;
use App\Actions\AdminTasks\CompleteReportBookReview;
use App\Actions\Courses\TransferDeletedCourseReportBooks;
use App\Livewire\Admin\Config\ReportBookLocationSettings;
use App\Livewire\Admin\AdminTasksList;
use App\Livewire\Admin\Tasks\AdminTaskDetail;
use App\Livewire\Admin\Dashboard\OpenJobs;
use App\Livewire\Tools\Signatures\SignatureForm;
use App\Services\ReportBooks\AdminTaskVisibility;
use App\Services\ReportBooks\SignatureEvidence;
use App\Models\{AdminTask, Course, ReportBook, User};
use App\Services\ApiUvs\AssetsApiServices\InstitutionsLoadService;
use App\Services\ReportBooks\LocationPolicy;
use App\Services\ReportBooks\StampStorage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\{DB, Gate, Http, Notification, Queue, Schema};
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ReportBookLocationSettingsTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync']);
        });
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Queue::fake(); Notification::fake(); Http::preventStrayRequests();
        CarbonImmutable::setTestNow('2026-10-19 10:00:00 UTC');
        \Illuminate\Support\Carbon::setTestNow('2026-10-19 10:00:00 UTC');
        Gate::define('settings.manage', fn ($user) => $user->name === 'Manager');
        Gate::define('jobs.view', fn () => true);
        $this->instance(InstitutionsLoadService::class, \Mockery::mock(InstitutionsLoadService::class)
            ->shouldReceive('getInstitutionOptions')->andReturn([['value' => '2', 'label' => 'Berlin'], ['value' => '1', 'label' => 'Hamburg']])->getMock());
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('role'); $t->timestamps(); });
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('persons', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('institut_id'); $t->json('programdata')->nullable(); $t->json('statusdata')->nullable(); $t->softDeletes(); });
        Schema::create('courses', function (Blueprint $t) { $t->id(); $t->string('title')->nullable(); $t->string('type')->nullable(); $t->string('vtz')->nullable(); $t->softDeletes(); $t->timestamps(); });
        Schema::create('course_participant_enrollments', function (Blueprint $t) { $t->id(); $t->integer('person_id'); $t->integer('course_id'); $t->json('source_snapshot')->nullable(); $t->boolean('is_active')->default(true); $t->softDeletes(); });
        Schema::create('report_books', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('course_id'); $t->string('massnahme_id')->nullable(); $t->string('title')->nullable(); $t->json('settings')->nullable(); $t->timestamps(); });
        Schema::create('report_book_entries', function (Blueprint $t) { $t->id(); $t->integer('report_book_id'); $t->integer('status'); $t->text('text')->nullable(); $t->timestamps(); });
        Schema::create('admin_tasks', function (Blueprint $t) { $t->id(); $t->integer('created_by'); $t->string('context_type'); $t->integer('context_id'); $t->string('task_type'); $t->integer('status'); $t->integer('priority')->default(2); $t->text('description')->nullable(); $t->timestamp('due_at')->nullable(); $t->integer('assigned_to')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamps(); });
        Schema::create('files', function (Blueprint $t) { $t->id(); $t->morphs('fileable'); $t->string('type'); $t->integer('user_id')->nullable(); $t->string('name')->nullable(); $t->string('path')->nullable(); $t->string('disk')->nullable(); $t->string('mime_type')->nullable(); $t->integer('size')->nullable(); $t->timestamps(); });
        Schema::create('activity_log', function (Blueprint $t) { $t->id(); $t->nullableMorphs('causer'); $t->timestamps(); });
        (require dirname(base_path()).'/base/database/migrations/2026_10_19_180000_create_report_book_location_policies.php')->up();
        $this->actingAs(User::withoutEvents(fn () => User::create(['name' => 'Manager', 'role' => 'staff'])));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow(); \Illuminate\Support\Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_default_required_and_explicit_preview_before_scheduling(): void
    {
        $component = Livewire::test(ReportBookLocationSettings::class)->set('instituteId', '2')
            ->assertSet('required', true)->assertSee('Berichtsheftpflicht');
        $component->set('required', false)->set('publicationDate', '2026-10-26')
            ->call('schedule')->assertHasErrors(['policy']);
        $this->assertSame(0, DB::table('report_book_policy_revisions')->count());
        $component->call('preview')->assertHasNoErrors()->call('schedule')->assertHasNoErrors()
            ->assertSee('Geplant ab 26.10.2026 00:00');
        $this->assertTrue(LocationPolicy::forInstitute(2)['required']);
        $this->assertSame('2026-10-25 23:00:00', DB::table('report_book_policy_revisions')->value('effective_at'));
        $this->assertSame(1, DB::table('report_book_policy_audit')->count());
    }

    public function test_changing_fields_invalidates_preview_and_unknown_sites_are_rejected(): void
    {
        $component = Livewire::test(ReportBookLocationSettings::class)->set('instituteId', '2')->call('preview');
        $component->set('required', false)->call('schedule')->assertHasErrors(['policy']);
        $component->set('instituteId', '999')->assertHasErrors(['instituteId']);
        $this->assertSame(0, DB::table('report_book_policy_revisions')->count());
    }

    public function test_permission_is_rechecked_after_opening_settings(): void
    {
        $component = Livewire::test(ReportBookLocationSettings::class)->set('instituteId', '2')->call('preview');
        Gate::define('settings.manage', fn () => false);
        $component->call('schedule')->assertForbidden();
        $this->assertSame(0, DB::table('report_book_policy_revisions')->count());
        Livewire::test(ReportBookLocationSettings::class)->assertForbidden();
    }

    public function test_stale_admin_and_missing_stamp_fail_without_writes(): void
    {
        $component = Livewire::test(ReportBookLocationSettings::class)->set('instituteId', '2')
            ->set('required', false)->set('publicationDate', '2026-10-26')->call('preview');
        LocationPolicy::schedule(2, ['required' => false, 'stamp_enabled' => false, 'publication_date' => '2026-10-27'], auth()->id(), 0);
        $component->call('schedule')->assertHasErrors(['publication_date'])->assertSee('zwischenzeitlich geändert');
        $this->assertSame(1, DB::table('report_book_policy_revisions')->count());
        $component->call('loadState')->set('stampEnabled', true)->call('preview')->assertHasErrors(['stampUpload']);
    }

    public function test_scheduled_policy_can_be_cancelled_and_history_is_kept(): void
    {
        $component = Livewire::test(ReportBookLocationSettings::class)->set('instituteId', '2')
            ->set('required', false)->set('publicationDate', '2026-10-26')->call('preview')->call('schedule');
        $component->call('cancel')->assertHasNoErrors()->assertSee('storniert');
        $this->assertSame(1, DB::table('report_book_policy_revisions')->count());
        $this->assertTrue(LocationPolicy::forInstitute(2, CarbonImmutable::parse('2026-10-27 UTC'))['required']);
    }

    public function test_bridge_failure_is_not_accepted_as_a_verified_stamp(): void
    {
        \App\Models\Setting::setValue('api', 'base_api_url', 'https://base.example.test');
        \App\Models\Setting::setValue('api', 'base_api_key', 'test-key');
        Http::fake(['base.example.test/*' => Http::response([], 503)]);
        $this->assertFalse(StampStorage::verify('reportbook-stamps/'.str_repeat('a', 64).'.png', str_repeat('a', 64), 'image/png'));
        Http::assertSent(fn ($request) => $request->hasHeader('X-API-KEY', 'test-key') && $request->url() === 'https://base.example.test/api/admin/reportbook-stamps/verify');
    }

    public function test_stamp_is_independent_of_required_and_audit_download_is_site_scoped(): void
    {
        \App\Models\Setting::setValue('api', 'base_api_url', 'https://base.example.test');
        \App\Models\Setting::setValue('api', 'base_api_key', 'test-key');
        $stamp = ['stamp_path' => 'reportbook-stamps/'.str_repeat('a', 64).'.png', 'stamp_sha256' => str_repeat('a', 64), 'stamp_mime' => 'image/png'];
        Http::fake([
            'base.example.test/api/admin/reportbook-stamps/verify' => Http::response(['verified' => true, 'preview' => null]),
            'base.example.test/api/admin/reportbook-stamps' => Http::response(['success' => true] + $stamp),
        ]);
        $component = Livewire::test(ReportBookLocationSettings::class)->set('instituteId', '2')->set('stampEnabled', true)
            ->set('stampUpload', \Illuminate\Http\UploadedFile::fake()->image('stamp.png'))->call('uploadStamp')
            ->set('publicationDate', '2026-10-26')->call('preview')->call('schedule')->assertHasNoErrors();
        $this->assertTrue((bool) DB::table('report_book_policy_revisions')->value('required'));
        $this->assertTrue((bool) DB::table('report_book_policy_revisions')->value('stamp_enabled'));
        $runId = DB::table('report_book_approval_runs')->value('id');
        $component->call('downloadAudit', $runId)->assertFileDownloaded('berichtsheft-protokoll-lauf-'.$runId.'.csv');
        $component->set('instituteId', '1')->call('downloadAudit', $runId)->assertNotFound();
        $component = Livewire::test(ReportBookLocationSettings::class)->set('instituteId', '2');
        Gate::define('settings.manage', fn () => false);
        $component->call('downloadAudit', $runId)->assertForbidden();
    }

    public function test_manual_review_does_not_change_previously_automated_entries(): void
    {
        [$book] = $this->bookAndTask();
        $protectedId = DB::table('report_book_entries')->insertGetId(['report_book_id' => $book->id, 'status' => 2, 'automated_approval_audit_id' => 77]);
        $submittedId = DB::table('report_book_entries')->insertGetId(['report_book_id' => $book->id, 'status' => 1]);
        $component = new class extends \App\Livewire\Admin\Tasks\AdminTaskDetail {
            public function applyForTest($book): void { $this->applyReportBookReview($book); }
        };
        $component->entryApprovals = [$protectedId => false, $submittedId => true];
        $component->applyForTest($book);
        $this->assertSame(2, (int) DB::table('report_book_entries')->where('id', $protectedId)->value('status'));
        $this->assertSame(77, (int) DB::table('report_book_entries')->where('id', $protectedId)->value('automated_approval_audit_id'));
        $this->assertSame(2, (int) DB::table('report_book_entries')->where('id', $submittedId)->value('status'));
    }

    public function test_policy_recheck_blocks_assignment_completion_and_manual_review(): void
    {
        [$book, $task] = $this->bookAndTask();
        LocationPolicy::schedule(2, ['required' => false, 'stamp_enabled' => false, 'publication_date' => '2026-10-19'], auth()->id(), 0);
        $this->assertNull(app(AssignAdminTask::class)->takeOver($task->id, 99, auth()->id()));
        $this->assertNull(app(AssignAdminTask::class)->release($task->id, auth()->id(), auth()->id(), true));
        $this->assertNull(app(AssignAdminTask::class)->complete($task->id, auth()->id(), auth()->id()));
        $called = false;
        $this->assertNull(app(CompleteReportBookReview::class)->handle($task->id, auth()->id(), $book->id, function () use (&$called) { $called = true; }));
        $this->assertFalse($called);
        $this->assertSame(1, (int) $task->fresh()->status);
    }

    public function test_mixed_review_preserves_manual_approvals_and_audit_entries_even_with_stale_loaded_entries(): void
    {
        [$book, $task] = $this->bookAndTask();
        $manualId = DB::table('report_book_entries')->insertGetId(['report_book_id' => $book->id, 'status' => 1, 'text' => 'Manuell bestätigt']);
        $preservedId = DB::table('report_book_entries')->insertGetId(['report_book_id' => $book->id, 'status' => 1, 'text' => 'Konservierter Nachweis']);
        $editableId = DB::table('report_book_entries')->insertGetId(['report_book_id' => $book->id, 'status' => 1, 'text' => 'Neue Einreichung']);
        $book->load('entries');
        DB::table('report_book_entries')->where('id', $manualId)->update(['status' => 2]);
        DB::table('report_book_policy_audit')->insert(['event_key' => 'entry:'.$preservedId.':signatures-preserved', 'event' => 'manual_signatures_preserved',
            'book_id' => $book->id, 'entry_id' => $preservedId, 'payload' => '{}', 'created_at' => now()]);
        $before = DB::table('report_book_entries')->whereIn('id', [$manualId, $preservedId])->orderBy('id')->get()->toJson();
        $component = new class extends AdminTaskDetail {
            public function applyForTest($book): void { $this->applyReportBookReview($book); }
            public function rejectionsForTest(): array { return $this->getRejectedEntryIds(); }
        };
        $component->task = $task;
        $component->entryApprovals = [$manualId => false, $preservedId => false, $editableId => false];
        $this->assertSame([$editableId], $component->rejectionsForTest());
        DB::transaction(fn () => $component->applyForTest($book));
        $this->assertSame($before, DB::table('report_book_entries')->whereIn('id', [$manualId, $preservedId])->orderBy('id')->get()->toJson());
        $this->assertSame(3, (int) DB::table('report_book_entries')->where('id', $editableId)->value('status'));
    }

    public function test_review_dialog_does_not_offer_toggles_for_approved_or_preserved_entries(): void
    {
        [$book, $task] = $this->bookAndTask();
        $manualId = DB::table('report_book_entries')->insertGetId(['report_book_id' => $book->id, 'status' => 2, 'text' => 'Manuelle Freigabe']);
        $preservedId = DB::table('report_book_entries')->insertGetId(['report_book_id' => $book->id, 'status' => 1, 'text' => 'Konservierte Freigabe']);
        $editableId = DB::table('report_book_entries')->insertGetId(['report_book_id' => $book->id, 'status' => 1, 'text' => 'Neue Einreichung']);
        DB::table('report_book_policy_audit')->insert(['event_key' => 'entry:'.$preservedId.':signatures-preserved', 'event' => 'manual_signatures_preserved',
            'book_id' => $book->id, 'entry_id' => $preservedId, 'payload' => '{}', 'created_at' => now()]);
        Livewire::test(AdminTaskDetail::class)->call('open', $task->id)->assertSet('entryApprovals', [$editableId => true])
            ->call('switchToContext')->assertDontSee('id="entry-approved-'.$manualId.'"', false)
            ->assertDontSee('id="entry-approved-'.$preservedId.'"', false)->assertSee('entry-approved-'.$editableId, false);
    }

    public function test_transfer_refuses_to_move_or_delete_automated_approval_evidence(): void
    {
        [$book] = $this->bookAndTask();
        DB::table('report_book_entries')->insert(['report_book_id' => $book->id, 'status' => 2, 'text' => 'Dauerhafter Nachweis', 'automated_approval_audit_id' => 77]);
        DB::table('courses')->insert(['id' => 2, 'title' => 'Ziel']);
        try {
            app(TransferDeletedCourseReportBooks::class)->handle(Course::findOrFail(1), Course::findOrFail(2));
            $this->fail('Transfer with automated provenance should be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('transfer', $e->errors());
        }
        $this->assertSame($book->id, (int) DB::table('report_book_entries')->value('report_book_id'));
        $this->assertSame(77, (int) DB::table('report_book_entries')->value('automated_approval_audit_id'));
    }

    public function test_disabled_open_tasks_are_removed_before_pagination_and_counting(): void
    {
        [$hiddenBook, $hiddenTask] = $this->bookAndTask();
        $hiddenTask->update(['status' => 0]);
        for ($i = 0; $i < 24; $i++) {
            $hiddenTask->replicate()->save();
        }
        DB::table('courses')->insert(['id' => 2, 'title' => 'Hamburger Kurs']);
        DB::table('persons')->insert(['id' => 2, 'user_id' => auth()->id(), 'institut_id' => 1]);
        DB::table('course_participant_enrollments')->insert(['person_id' => 2, 'course_id' => 2]);
        $visibleBook = ReportBook::create(['user_id' => auth()->id(), 'course_id' => 2, 'title' => 'Sichtbares Buch']);
        for ($i = 0; $i < 21; $i++) {
            $copy = $hiddenTask->replicate();
            $copy->context_id = $visibleBook->id;
            $copy->save();
        }
        LocationPolicy::schedule(2, ['required' => false, 'stamp_enabled' => false, 'publication_date' => '2026-10-19'], auth()->id(), 0);
        $component = new AdminTasksList;
        $data = $component->render()->getData();
        $this->assertSame(21, $data['openCount']);
        $this->assertSame(21, $data['tasks']->total());
        $this->assertSame(20, $data['tasks']->count());
        $this->assertTrue($data['tasks']->every(fn ($task) => (int) $task->context_id === $visibleBook->id));
        // The user's first person is Berlin, but this book belongs to the Hamburg enrollment.
        $component->filterInstitution = 1;
        $this->assertSame(21, $component->render()->getData()['tasks']->total());
        $component->filterInstitution = 2;
        $this->assertSame(0, $component->render()->getData()['openCount']);
        $this->assertSame(0, $component->render()->getData()['tasks']->total());
        $dashboard = new OpenJobs;
        $dashboard->loadJobs();
        $this->assertCount(6, $dashboard->jobs);
        foreach ($dashboard->jobs as $job) $this->assertStringContainsString('Hamburger Kurs', $job['context_text']);
        $this->assertStringContainsString('21 offene Aufgaben', view('components.navigation.open-tasks-counter')->render());
        DB::table('admin_tasks')->update(['assigned_to' => null]);
        $this->assertSame(21, AdminTaskVisibility::apply(AdminTask::whereNull('assigned_to'))->count());
    }

    public function test_disabled_task_direct_access_and_already_open_dialog_are_blocked(): void
    {
        [, $task] = $this->bookAndTask();
        $dialog = Livewire::test(AdminTaskDetail::class)->call('open', $task->id)->assertSet('showDetailModal', true);
        LocationPolicy::schedule(2, ['required' => false, 'stamp_enabled' => false, 'publication_date' => '2026-10-19'], auth()->id(), 0);
        $dialog->call('switchToContext')->assertForbidden();
        Livewire::test(AdminTaskDetail::class)->call('open', $task->id)->assertForbidden();
        $task->update(['status' => 0, 'assigned_to' => null]);
        Livewire::test(AdminTaskDetail::class)->call('open', $task->id)->assertForbidden();
    }

    public function test_completed_task_history_only_renders_approved_entries_when_disabled_or_conflicting(): void
    {
        [$book, $task] = $this->bookAndTask();
        $task->update(['status' => 2]);
        foreach ([0 => 'VerborgenerEntwurf', 1 => 'VerborgeneEinreichung', 2 => 'SichtbarerNachweis', 3 => 'VerborgeneAblehnung'] as $status => $text) {
            DB::table('report_book_entries')->insert(['report_book_id' => $book->id, 'status' => $status, 'text' => $text]);
        }
        LocationPolicy::schedule(2, ['required' => false, 'stamp_enabled' => false, 'publication_date' => '2026-10-19'], auth()->id(), 0);
        $this->assertArchivedTask($task);
        DB::table('persons')->insert(['id' => 2, 'user_id' => auth()->id(), 'institut_id' => 1]);
        DB::table('course_participant_enrollments')->insert(['person_id' => 2, 'course_id' => 1]);
        $this->assertSame('conflict', LocationPolicy::forBook($book)['mode']);
        $this->assertArchivedTask($task);
        $list = new AdminTasksList;
        $list->filterStatus = 2;
        $list->filterInstitution = 1;
        $this->assertSame(1, $list->render()->getData()['tasks']->total());
        $list->filterInstitution = 2;
        $this->assertSame(1, $list->render()->getData()['tasks']->total());
        $task->update(['status' => 1]);
        $list->filterStatus = null;
        $this->assertSame(0, $list->render()->getData()['tasks']->total());
        Livewire::test(AdminTaskDetail::class)->call('open', $task->id)->assertForbidden();
    }

    private function assertArchivedTask(AdminTask $task): void
    {
        Livewire::test(AdminTaskDetail::class)->call('open', $task->id)
            ->assertSet('entryApprovals', [])->call('switchToContext')
            ->assertSee('SichtbarerNachweis')->assertSee('1 Eintrag')
            ->assertDontSee('VerborgenerEntwurf')->assertDontSee('VerborgeneEinreichung')->assertDontSee('VerborgeneAblehnung');
    }

    public function test_stale_signature_dialog_is_rejected_before_any_file_io(): void
    {
        [$book] = $this->bookAndTask();
        $dialog = Livewire::test(SignatureForm::class)->call('openSignatureForm', [
            'fileableType' => ReportBook::class, 'fileableId' => $book->id, 'fileType' => 'sign_reportbook_trainer',
        ]);
        LocationPolicy::schedule(2, ['required' => false, 'stamp_enabled' => false, 'publication_date' => '2026-10-19'], auth()->id(), 0);
        $dialog->call('save')->assertForbidden();
        Http::assertNothingSent();
        $this->assertSame(0, DB::table('files')->count());
    }

    public function test_signature_requires_current_reviewer_permission_and_never_replaces_existing_files(): void
    {
        [$book, $task] = $this->bookAndTask();
        $makeDialog = fn () => Livewire::test(SignatureForm::class)->set('fileableType', ReportBook::class)->set('fileableId', $book->id);
        Gate::define('jobs.view', fn () => false);
        $makeDialog()->call('save')->assertForbidden();
        Gate::define('jobs.view', fn () => true);
        $task->update(['assigned_to' => 999]);
        $makeDialog()->call('save')->assertForbidden();
        $task->update(['assigned_to' => auth()->id()]);
        $makeDialog()->set('fileType', 'sign_reportbook_participant')->call('save')->assertForbidden();
        $makeDialog()->set('existingPath', 'signatures/report-book/1/old.png')->call('save')->assertForbidden();
        DB::table('files')->insert(['fileable_type' => ReportBook::class, 'fileable_id' => $book->id, 'type' => 'sign_reportbook_trainer', 'path' => 'old.png']);
        $makeDialog()->call('save')->assertStatus(409);
        Http::assertNothingSent();
        $this->assertSame('old.png', DB::table('files')->value('path'));
    }

    public function test_invalidated_signature_stays_preserved_and_a_new_signature_becomes_current(): void
    {
        [$book] = $this->bookAndTask();
        DB::table('report_book_entries')->insert(['report_book_id' => $book->id, 'status' => 2]);
        $oldId = DB::table('files')->insertGetId(['fileable_type' => ReportBook::class, 'fileable_id' => $book->id, 'type' => 'sign_reportbook_trainer', 'path' => 'old.png']);
        $book->forceFill(['settings' => ['reportbook_invalidated_signature_ids' => [$oldId]]])->save();
        $this->assertNull($book->trainerSignatureFile());
        \App\Models\Setting::setValue('api', 'base_api_url', 'https://base.example.test');
        \App\Models\Setting::setValue('api', 'base_api_key', 'test-key');
        Http::fake(['base.example.test/api/admin/upload' => Http::response(['success' => true, 'path' => 'signatures/report-book-uploads/1/new.png'])]);
        Livewire::test(SignatureForm::class)->set('fileableType', ReportBook::class)->set('fileableId', $book->id)
            ->set('upload', \Illuminate\Http\UploadedFile::fake()->image('signature.png'))->call('save')->assertDispatched('signatureCompleted');
        $this->assertSame(2, DB::table('files')->count());
        $this->assertSame('signatures/report-book-uploads/1/new.png', $book->fresh()->trainerSignatureFile()->path);
        try {
            \App\Models\File::findOrFail($oldId)->delete();
            $this->fail('Preserved signature deletion must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }
        $this->assertSame('old.png', DB::table('files')->where('id', $oldId)->value('path'));
        Http::assertSentCount(1);
    }

    public function test_signature_rechecks_publication_after_upload_and_removes_only_new_orphan(): void
    {
        [$book] = $this->bookAndTask();
        LocationPolicy::schedule(2, ['required' => false, 'stamp_enabled' => false, 'publication_date' => '2026-10-26'], auth()->id(), 0);
        \App\Models\Setting::setValue('api', 'base_api_url', 'https://base.example.test');
        \App\Models\Setting::setValue('api', 'base_api_key', 'test-key');
        Http::fake([
            'base.example.test/api/admin/upload' => function ($request) {
                $this->assertStringContainsString('signatures/report-book-uploads/1', $request->body());
                CarbonImmutable::setTestNow('2026-10-26 00:00:00 UTC');
                \Illuminate\Support\Carbon::setTestNow('2026-10-26 00:00:00 UTC');
                return Http::response(['success' => true, 'path' => 'signatures/report-book-uploads/1/new-only.png']);
            },
            'base.example.test/api/admin/delete' => Http::response(['success' => true]),
        ]);
        Livewire::test(SignatureForm::class)->set('fileableType', ReportBook::class)->set('fileableId', $book->id)
            ->set('upload', \Illuminate\Http\UploadedFile::fake()->image('signature.png'))->call('save')->assertForbidden();
        $this->assertSame(0, DB::table('files')->count());
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request['path'] === 'signatures/report-book-uploads/1/new-only.png');
    }

    public function test_submitted_book_signature_model_and_media_deletion_are_blocked_before_io(): void
    {
        [$book] = $this->bookAndTask();
        DB::table('report_book_entries')->insert(['report_book_id' => $book->id, 'status' => 1]);
        $path = 'signatures/report-book-uploads/'.$book->id.'/submitted.png';
        $id = DB::table('files')->insertGetId(['fileable_type' => ReportBook::class, 'fileable_id' => $book->id, 'type' => 'sign_reportbook_trainer', 'path' => $path]);
        try {
            \App\Models\File::findOrFail($id)->delete();
            $this->fail('A future approval must not race with generic File deletion.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }
        try {
            app(\App\Http\Controllers\MediaController::class)->destroy(\Illuminate\Http\Request::create('/admin/media/delete', 'DELETE', ['path' => $path]));
            $this->fail('Referenced report-book signatures must not reach generic remote deletion.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        Http::assertNothingSent();
        $this->assertSame($path, DB::table('files')->where('id', $id)->value('path'));
        $this->assertTrue(SignatureEvidence::pathIsPreserved('signatures/report-book/1/legacy.png'));
    }

    public function test_signature_audit_paths_outlive_source_rows_and_block_transfer(): void
    {
        [$book] = $this->bookAndTask();
        DB::table('report_book_policy_audit')->insert(['event_key' => 'test-manual-preservation', 'event' => 'manual_signatures_preserved', 'book_id' => $book->id,
            'payload' => json_encode(['signature_files' => [['path' => 'preserved.png']]]), 'created_at' => now()]);
        $this->assertTrue(SignatureEvidence::pathIsPreserved('preserved.png'));
        DB::table('courses')->insert(['id' => 2, 'title' => 'Ziel']);
        try {
            app(TransferDeletedCourseReportBooks::class)->handle(Course::findOrFail(1), Course::findOrFail(2));
            $this->fail('Preserved manual signature evidence must block destructive transfer.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('transfer', $e->errors());
        }
        $this->assertSame(1, (int) $book->fresh()->course_id);
    }

    private function bookAndTask(): array
    {
        DB::table('courses')->insert(['id' => 1, 'title' => 'Kurs']);
        DB::table('persons')->insert(['id' => 1, 'user_id' => auth()->id(), 'institut_id' => 2]);
        DB::table('course_participant_enrollments')->insert(['person_id' => 1, 'course_id' => 1]);
        $book = ReportBook::create(['user_id' => auth()->id(), 'course_id' => 1, 'title' => 'Buch']);
        $task = AdminTask::create(['created_by' => auth()->id(), 'context_type' => ReportBook::class, 'context_id' => $book->id,
            'task_type' => 'reportbook_review', 'status' => 1, 'assigned_to' => auth()->id()]);
        return [$book, $task];
    }
}
