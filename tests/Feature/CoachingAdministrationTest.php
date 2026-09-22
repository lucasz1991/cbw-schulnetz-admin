<?php

namespace Tests\Feature;

use App\Livewire\Admin\Coaching\Contracts;
use App\Models\{CoachingContract, Person, User};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\{Gate, Queue, Schema};
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CoachingAdministrationTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'session.driver' => 'array', 'coaching.enabled' => true]);
        });
        $app->make(Kernel::class)->bootstrap(); return $app;
    }

    protected function setUp(): void
    {
        parent::setUp(); Queue::fake();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('role'); $t->integer('current_team_id')->nullable(); $t->timestamps(); });
        Schema::create('teams', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('name'); $t->boolean('personal_team')->default(false); $t->json('rbac_permissions')->nullable(); $t->timestamps(); });
        Schema::create('persons', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('institut_id'); $t->string('person_id'); $t->string('role'); $t->string('nachname')->default('Test'); $t->string('vorname')->default('Person'); $t->timestamps(); $t->softDeletes(); });
        // Base owns the shared schema; run its migration only against this isolated test database.
        $migration = dirname(base_path()).'/base/database/migrations/2026_09_17_080000_create_coaching_planning_tables.php';
        $this->assertFileExists($migration, 'Die Admin-Integrationstests benötigen den benachbarten Base-Checkout.');
        (require $migration)->up();
        (require dirname(base_path()).'/base/database/migrations/2026_09_17_110000_add_uvs_tutor_to_coaching_contracts.php')->up();
    }

    private function user(string $role, int $institute = 1, bool $permission = false): User
    {
        $user = User::create(['name' => 'Test '.$role, 'role' => $role]);
        Person::withoutEvents(fn () => Person::create(['user_id' => $user->id, 'institut_id' => $institute, 'person_id' => $institute.'-'.$user->id, 'role' => $role]));
        if ($permission) {
            $team = new \App\Models\Team();
            $team->forceFill(['user_id' => $user->id, 'name' => 'Test', 'personal_team' => false, 'rbac_permissions' => ['coaching.manage' => true]])->save();
            $user->forceFill(['current_team_id' => $team->id])->save();
        }
        return $user;
    }

    private function contract(int $institute = 1): CoachingContract
    {
        return CoachingContract::create(['uuid' => (string)Str::uuid(), 'uvs_contract_id' => $institute,
            'institut_id' => $institute, 'uvs_person_id' => $institute.'-900', 'beratung_id' => 'test', 'title' => 'Test Einzelcoaching',
            'agreed_minutes' => 180, 'unit_minutes' => 45, 'contract_version' => str_repeat('a',64), 'last_imported_at' => now()]);
    }

    public function test_admin_view_displays_uvs_assignment_and_message_status_without_assignment_action(): void
    {
        $admin = $this->user('admin'); $tutor = $this->user('tutor'); $contract = $this->contract();
        $contract->update(['tutor_person_id' => $tutor->persons()->first()->id, 'uvs_tutor_person_id' => '1-200', 'tutor_notified_at' => now()]);
        $this->actingAs($admin);
        Livewire::test(Contracts::class)->assertSee('Mit UVS abgleichen')->assertSee('Auswahl im UVS-Vertrag')
            ->assertSee('Mitteilung im Schulnetz zugestellt')->assertDontSee('Zuordnen');
        $this->assertFalse(method_exists(Contracts::class, 'assign'));
        $this->assertDatabaseCount('coaching_assignment_history', 0);
    }

    public function test_staff_without_dedicated_permission_cannot_open_management(): void
    {
        $this->actingAs($this->user('staff'));
        Livewire::test(Contracts::class)->assertForbidden();
    }

    public function test_staff_with_permission_cannot_retry_a_foreign_institute_contract(): void
    {
        $staff = $this->user('staff',1,true); $foreign = $this->contract(2); $this->actingAs($staff);
        $this->assertTrue(Gate::allows('coaching.manage'));
        $component = Livewire::test(Contracts::class)->assertDontSee('Test Einzelcoaching');
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $component->call('retry', $foreign->id);
    }

    public function test_missing_tutor_account_is_visible_to_admin(): void
    {
        $this->actingAs($this->user('admin')); $contract = $this->contract();
        $contract->update(['uvs_tutor_person_id' => '1-200']);
        Livewire::test(Contracts::class)->assertSee('1-200')->assertSee('Dozentenkonto noch nicht verknüpft');
    }

    public function test_admin_import_delivers_once_and_links_to_the_configured_base_site(): void
    {
        $this->user('admin'); $tutor = $this->user('tutor');
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('messages', function (Blueprint $t) { $t->id(); $t->string('subject'); $t->text('message'); $t->integer('from_user'); $t->integer('to_user'); $t->integer('status'); $t->timestamps(); });
        \App\Models\Setting::setValue('api', 'base_api_url', 'https://schulnetz.example.test/portal');
        $row = ['id' => 11, 'institut_id' => 1, 'person_id' => '1-900', 'beratung_id' => 'test',
            'tutor_person_id' => $tutor->persons()->first()->person_id, 'title' => 'Einzelcoaching',
            'agreed_minutes' => 180, 'unit_minutes' => 45, 'version' => str_repeat('a',64), 'status' => 'active'];
        app(\App\Services\Coaching\SyncService::class)->importContract($row);
        app(\App\Services\Coaching\SyncService::class)->importContract($row);
        $this->assertDatabaseCount('messages', 1);
        $message = \App\Models\Message::firstOrFail();
        $this->assertSame($tutor->id, (int)$message->to_user);
        $this->assertStringContainsString('https://schulnetz.example.test/portal/coaching?contract=', $message->message);
    }

    public function test_admin_projects_standard_course_and_keeps_attendance_local(): void
    {
        // These shared tables are owned by the Base migrations, not the Admin repository.
        Schema::create('courses', function (Blueprint $t) {
            $t->id(); $t->string('klassen_id')->unique(); $t->string('termin_id')->nullable();
            $t->integer('institut_id'); $t->string('vtz'); $t->string('type'); $t->string('title');
            $t->integer('primary_tutor_person_id'); $t->boolean('is_active');
            $t->date('planned_start_date'); $t->date('planned_end_date'); $t->json('settings'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('course_days', function (Blueprint $t) {
            $t->id(); $t->integer('course_id'); $t->date('date'); $t->time('start_time'); $t->time('end_time');
            $t->float('std')->nullable(); $t->json('day_sessions'); $t->json('attendance_data')->nullable();
            $t->string('topic'); $t->string('type'); $t->integer('note_status')->default(0); $t->json('settings')->nullable(); $t->timestamps();
            $t->timestamp('attendance_updated_at')->nullable(); $t->timestamp('attendance_last_synced_at')->nullable();
        });
        Schema::create('course_participant_enrollments', function (Blueprint $t) {
            $t->id(); $t->integer('course_id'); $t->integer('person_id'); $t->string('teilnehmer_id')->nullable();
            $t->string('klassen_id'); $t->string('vtz'); $t->string('status'); $t->boolean('is_active');
            foreach (['tn_baustein_id', 'baustein_id', 'termin_id', 'kurzbez_ba', 'results', 'notes', 'source_last_upd', 'last_synced_at'] as $field) $t->string($field)->nullable();
            $t->json('source_snapshot'); $t->timestamps(); $t->softDeletes();
        });
        $participant = $this->user('participant')->persons()->first(); $tutor = $this->user('tutor')->persons()->first();
        $contract = $this->contract();
        $contract->update(['participant_person_id' => $participant->id, 'tutor_person_id' => $tutor->id, 'revision' => 1]);
        $items = app(\App\Services\Coaching\PlanValidator::class)->validate([
            ['id' => (string)Str::uuid(), 'date' => '2026-09-21', 'start' => '09:00', 'end' => '12:00',
             'topic' => 'Coaching', 'format' => 'online', 'location' => 'Online'],
        ], 180);
        $plan = $contract->plans()->create(['revision' => 1, 'contract_version' => $contract->contract_version,
            'participant_person_id' => $participant->id, 'tutor_person_id' => $tutor->id, 'created_by' => $tutor->user_id,
            'items' => $items, 'status' => 'confirmed', 'confirmed_at' => now(), 'participant_confirmed_at' => now(), 'tutor_confirmed_at' => now()]);
        $contract->update(['confirmed_plan_id' => $plan->id]);
        $course = app(\App\Services\Coaching\CourseProjector::class)->project($contract, $plan);
        $day = $course->days()->firstOrFail();
        $this->assertSame(4.0, (float)$day->std);
        $this->assertSame($participant->id, $course->enrollments()->firstOrFail()->person_id);
        $api = \Mockery::mock(\App\Services\ApiUvs\ApiUvsService::class);
        $api->shouldNotReceive('request'); $api->shouldNotReceive('loadCourseDayAttendanceData'); $api->shouldNotReceive('loadCourseResultsData');
        $loader = new \App\Services\ApiUvs\CourseApiServices\CourseUvsDirectLoadService($api);
        $sync = new \App\Services\ApiUvs\CourseApiServices\CourseDayAttendanceSyncService($loader, $api);
        $before = $day->attendance_data;
        $this->assertTrue($loader->loadResults($course));
        $this->assertTrue($loader->loadAttendanceForDay($day));
        $this->assertTrue($sync->syncToRemote($day));
        $this->assertTrue($sync->loadFromRemote($day));
        $this->assertSame($before, $day->fresh()->attendance_data);
        $this->app->instance(\App\Services\ApiUvs\CourseApiServices\CourseDayAttendanceSyncService::class, $sync);
        $this->actingAs($this->user('admin'));
        $editor = new \App\Livewire\Admin\Courses\AttendanceEditorModal();
        $editor->open($day->id); $editor->markPresent($participant->id);
        $this->assertTrue($editor->isCoaching);
        $this->assertNull($editor->syncError);
        $this->assertTrue(data_get($day->fresh()->attendance_data, 'participants.'.$participant->id.'.present'));
        $this->assertSame('local', data_get($day->fresh()->attendance_data, 'participants.'.$participant->id.'.state'));
    }
}
