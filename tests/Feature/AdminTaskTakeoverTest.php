<?php

namespace Tests\Feature;

use App\Actions\AdminTasks\AssignAdminTask;
use App\Actions\AdminTasks\CompleteReportBookReview;
use App\Livewire\Admin\Tasks\AdminTaskDetail;
use App\Models\AdminTask;
use App\Models\ReportBook;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTaskTakeoverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'admin_task_takeover_testing');
        config()->set('database.connections.admin_task_takeover_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('admin_task_takeover_testing');
        DB::reconnect('admin_task_takeover_testing');

        $this->createSchema();
    }

    public function test_authorized_staff_can_take_over_an_active_reportbook_task(): void
    {
        $creator = $this->createStaff('Teilnehmer', false);
        $previousAssignee = $this->createStaff('Bisheriger Bearbeiter');
        $replacement = $this->createStaff('Vertretung');
        $task = $this->createTask(
            $creator,
            AdminTask::TYPE_REPORTBOOK_REVIEW,
            AdminTask::STATUS_IN_PROGRESS,
            $previousAssignee->id
        );

        $this->actingAs($replacement);

        Livewire::test(AdminTaskDetail::class)
            ->call('open', ['taskId' => $task->id])
            ->assertSet('loadedAssignedTo', $previousAssignee->id)
            ->assertSee("Von {$previousAssignee->name} übernehmen")
            ->call('takeOver')
            ->assertSet('loadedAssignedTo', $replacement->id)
            ->assertDispatched('taskAssigned');

        $task->refresh();

        $this->assertSame($replacement->id, (int) $task->assigned_to);
        $this->assertSame(AdminTask::STATUS_IN_PROGRESS, (int) $task->status);
        $this->assertNull($task->completed_at);
    }

    public function test_standard_unassigned_claim_still_works(): void
    {
        $creator = $this->createStaff('Teilnehmer', false);
        $assignee = $this->createStaff('Bearbeiter');
        $task = $this->createTask(
            $creator,
            AdminTask::TYPE_REPORTBOOK_REVIEW,
            AdminTask::STATUS_OPEN
        );

        $this->actingAs($assignee);

        Livewire::test(AdminTaskDetail::class)
            ->call('open', ['taskId' => $task->id])
            ->assertSee('Übernehmen')
            ->call('assignToMe')
            ->assertSet('loadedAssignedTo', $assignee->id);

        $task->refresh();

        $this->assertSame($assignee->id, (int) $task->assigned_to);
        $this->assertSame(AdminTask::STATUS_IN_PROGRESS, (int) $task->status);
    }

    public function test_stale_takeover_does_not_overwrite_a_newer_assignment(): void
    {
        $creator = $this->createStaff('Teilnehmer', false);
        $previousAssignee = $this->createStaff('Erster Bearbeiter');
        $newerAssignee = $this->createStaff('Neuerer Bearbeiter');
        $replacement = $this->createStaff('Vertretung');
        $task = $this->createTask(
            $creator,
            AdminTask::TYPE_REPORTBOOK_REVIEW,
            AdminTask::STATUS_IN_PROGRESS,
            $previousAssignee->id
        );

        $task->forceFill(['assigned_to' => $newerAssignee->id])->save();

        $result = app(AssignAdminTask::class)->takeOver(
            $task->id,
            $replacement->id,
            $previousAssignee->id
        );

        $this->assertNull($result);
        $this->assertSame($newerAssignee->id, (int) $task->fresh()->assigned_to);
    }

    public function test_completed_and_non_reportbook_tasks_cannot_be_taken_over(): void
    {
        $creator = $this->createStaff('Teilnehmer', false);
        $previousAssignee = $this->createStaff('Bisheriger Bearbeiter');
        $replacement = $this->createStaff('Vertretung');

        $completed = $this->createTask(
            $creator,
            AdminTask::TYPE_REPORTBOOK_REVIEW,
            AdminTask::STATUS_COMPLETED,
            $previousAssignee->id
        );
        $otherType = $this->createTask(
            $creator,
            'user_request_review',
            AdminTask::STATUS_IN_PROGRESS,
            $previousAssignee->id
        );

        $action = app(AssignAdminTask::class);

        $this->assertNull($action->takeOver($completed->id, $replacement->id, $previousAssignee->id));
        $this->assertNull($action->takeOver($otherType->id, $replacement->id, $previousAssignee->id));
        $this->assertSame($previousAssignee->id, (int) $completed->fresh()->assigned_to);
        $this->assertSame($previousAssignee->id, (int) $otherType->fresh()->assigned_to);
    }

    public function test_staff_without_jobs_permission_is_forbidden_server_side(): void
    {
        $unauthorized = $this->createStaff('Ohne Jobs-Recht', false);

        $this->actingAs($unauthorized);

        Livewire::test(AdminTaskDetail::class)
            ->assertForbidden();
    }

    public function test_stale_reviewer_cannot_apply_side_effects_after_takeover(): void
    {
        $creator = $this->createStaff('Teilnehmer', false);
        $previousAssignee = $this->createStaff('Bisheriger Prüfer');
        $replacement = $this->createStaff('Neue Prüferin');
        $reportBook = ReportBook::query()->create([
            'user_id' => $creator->id,
            'title' => 'Test-Berichtsheft',
        ]);
        $task = $this->createTask(
            $creator,
            AdminTask::TYPE_REPORTBOOK_REVIEW,
            AdminTask::STATUS_IN_PROGRESS,
            $previousAssignee->id
        );
        $task->forceFill([
            'context_type' => ReportBook::class,
            'context_id' => $reportBook->id,
            'assigned_to' => $replacement->id,
        ])->save();

        $result = app(CompleteReportBookReview::class)->handle(
            $task->id,
            $previousAssignee->id,
            $reportBook->id,
            function (): void {
                DB::table('review_side_effects')->insert(['created_at' => now()]);
            }
        );

        $this->assertNull($result);
        $this->assertSame(0, DB::table('review_side_effects')->count());
        $this->assertSame($replacement->id, (int) $task->fresh()->assigned_to);
        $this->assertSame(AdminTask::STATUS_IN_PROGRESS, (int) $task->fresh()->status);
    }

    public function test_review_side_effects_and_completion_share_one_transaction(): void
    {
        $creator = $this->createStaff('Teilnehmer', false);
        $assignee = $this->createStaff('Prüferin');
        $reportBook = ReportBook::query()->create([
            'user_id' => $creator->id,
            'title' => 'Test-Berichtsheft',
        ]);
        $task = $this->createTask(
            $creator,
            AdminTask::TYPE_REPORTBOOK_REVIEW,
            AdminTask::STATUS_IN_PROGRESS,
            $assignee->id
        );
        $task->forceFill([
            'context_type' => ReportBook::class,
            'context_id' => $reportBook->id,
        ])->save();

        $result = app(CompleteReportBookReview::class)->handle(
            $task->id,
            $assignee->id,
            $reportBook->id,
            function (): void {
                DB::table('review_side_effects')->insert(['created_at' => now()]);
            }
        );

        $this->assertNotNull($result);
        $this->assertSame(1, DB::table('review_side_effects')->count());
        $this->assertSame(AdminTask::STATUS_COMPLETED, (int) $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_stale_original_assignee_cannot_complete_or_release_after_takeover(): void
    {
        $creator = $this->createStaff('Teilnehmer', false);
        $previousAssignee = $this->createStaff('Bisheriger Bearbeiter');
        $replacement = $this->createStaff('Neue Bearbeiterin');
        $task = $this->createTask(
            $creator,
            AdminTask::TYPE_REPORTBOOK_REVIEW,
            AdminTask::STATUS_IN_PROGRESS,
            $replacement->id
        );
        $action = app(AssignAdminTask::class);

        $completed = $action->complete(
            $task->id,
            $previousAssignee->id,
            $previousAssignee->id
        );
        $released = $action->release(
            $task->id,
            $previousAssignee->id,
            $previousAssignee->id,
            false
        );

        $this->assertNull($completed);
        $this->assertNull($released);
        $this->assertSame($replacement->id, (int) $task->fresh()->assigned_to);
        $this->assertSame(AdminTask::STATUS_IN_PROGRESS, (int) $task->fresh()->status);
    }

    public function test_failed_review_rolls_back_side_effects_and_keeps_task_active(): void
    {
        $creator = $this->createStaff('Teilnehmer', false);
        $assignee = $this->createStaff('Prüferin');
        $reportBook = ReportBook::query()->create([
            'user_id' => $creator->id,
            'title' => 'Test-Berichtsheft',
        ]);
        $task = $this->createTask(
            $creator,
            AdminTask::TYPE_REPORTBOOK_REVIEW,
            AdminTask::STATUS_IN_PROGRESS,
            $assignee->id
        );
        $task->forceFill([
            'context_type' => ReportBook::class,
            'context_id' => $reportBook->id,
        ])->save();

        try {
            app(CompleteReportBookReview::class)->handle(
                $task->id,
                $assignee->id,
                $reportBook->id,
                function (): void {
                    DB::table('review_side_effects')->insert(['created_at' => now()]);

                    throw new \RuntimeException('Review fehlgeschlagen');
                }
            );

            $this->fail('Die Review-Exception wurde nicht weitergegeben.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Review fehlgeschlagen', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('review_side_effects')->count());
        $this->assertSame(AdminTask::STATUS_IN_PROGRESS, (int) $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);
    }

    protected function createStaff(string $name, bool $canManageJobs = true): User
    {
        $teamId = DB::table('teams')->insertGetId([
            'name' => "Team {$name}",
            'personal_team' => false,
            'rbac_permissions' => json_encode([
                'jobs.view' => $canManageJobs,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'password' => 'secret',
            'role' => 'staff',
            'status' => true,
        ]);

        $user->forceFill(['current_team_id' => $teamId])->save();

        return $user->fresh();
    }

    protected function createTask(
        User $creator,
        string $taskType,
        int $status,
        ?int $assignedTo = null
    ): AdminTask {
        return AdminTask::query()->create([
            'created_by' => $creator->id,
            'task_type' => $taskType,
            'description' => 'Testaufgabe',
            'status' => $status,
            'priority' => AdminTask::PRIORITY_NORMAL,
            'assigned_to' => $assignedTo,
            'completed_at' => $status === AdminTask::STATUS_COMPLETED ? now() : null,
        ]);
    }

    protected function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->default('staff');
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('current_team_id')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->boolean('personal_team')->default(false);
            $table->text('rbac_permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('vorname')->nullable();
            $table->string('nachname')->nullable();
            $table->string('email_priv')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('admin_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->nullableMorphs('context');
            $table->string('task_type');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('status')->default(AdminTask::STATUS_OPEN);
            $table->unsignedTinyInteger('priority')->default(AdminTask::PRIORITY_NORMAL);
            $table->timestamp('due_at')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('report_books', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('massnahme_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('review_side_effects', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->nullable();
        });
    }
}
