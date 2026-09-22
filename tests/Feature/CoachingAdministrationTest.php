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
                'cache.default' => 'array', 'session.driver' => 'array']);
        });
        $app->make(Kernel::class)->bootstrap(); return $app;
    }

    protected function setUp(): void
    {
        parent::setUp(); Queue::fake(); \Illuminate\Support\Facades\Notification::fake();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('role'); $t->integer('current_team_id')->nullable(); $t->timestamps(); });
        Schema::create('teams', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('name'); $t->boolean('personal_team')->default(false); $t->json('rbac_permissions')->nullable(); $t->timestamps(); });
        Schema::create('persons', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('institut_id'); $t->string('person_id'); $t->string('role'); $t->string('nachname')->default('Test'); $t->string('vorname')->default('Person'); $t->timestamps(); $t->softDeletes(); });
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        \App\Models\Setting::setValue('coaching', 'enabled', true);
        // Base owns the shared schema; run its migration only against this isolated test database.
        $migration = dirname(base_path()).'/base/database/migrations/2026_09_17_080000_create_coaching_planning_tables.php';
        $this->assertFileExists($migration, 'Die Admin-Integrationstests benötigen den benachbarten Base-Checkout.');
        (require $migration)->up();
        (require dirname(base_path()).'/base/database/migrations/2026_09_17_110000_add_uvs_tutor_to_coaching_contracts.php')->up();
        (require dirname(base_path()).'/base/database/migrations/2026_09_22_100000_create_coaching_notices.php')->up();
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

    public function test_superadmin_can_toggle_the_shared_setting_without_config_or_cache_changes(): void
    {
        $this->actingAs($this->user('admin'));
        \App\Models\Setting::where('type', 'coaching')->delete();
        config(['coaching.enabled' => true]); // A stale config cache must not enable the feature.
        \Illuminate\Support\Facades\Cache::put('settings.coaching.enabled', true, 3600);
        $this->assertFalse(\App\Services\Coaching\Access::available());
        $component = Livewire::test(\App\Livewire\Admin\Config\CoachingSettings::class)
            ->assertSet('enabled', false)->assertSee('Einzelcoaching im Schulnetz aktivieren');
        $component->set('enabled', true)->call('save')->assertHasNoErrors()->assertSee('Einzelcoaching-Einstellung gespeichert.');
        $this->assertTrue(\App\Services\Coaching\Access::available());
        Livewire::test(\App\Livewire\Admin\Config\CoachingSettings::class)->assertSet('enabled', true);
        $component->set('enabled', false)->call('save')->assertHasNoErrors();
        $this->assertFalse(\App\Services\Coaching\Access::available());
    }

    public function test_other_roles_cannot_open_coaching_settings_even_with_settings_permission(): void
    {
        Gate::define('settings.manage', fn () => true);
        foreach (['staff', 'tutor', 'guest'] as $role) {
            $this->actingAs($this->user($role));
            Livewire::test(\App\Livewire\Admin\Config\CoachingSettings::class)->assertForbidden();
        }
    }

    public function test_settings_navigation_only_renders_coaching_for_the_api_superadmin_role(): void
    {
        $view = file_get_contents(resource_path('views/livewire/admin-config.blade.php'));
        $navigation = substr($view, 0, strpos($view, '        <!-- Tab Content -->'));
        $this->actingAs($this->user('admin'));
        $html = \Illuminate\Support\Facades\Blade::render($navigation);
        $this->assertStringContainsString("activeTab = 'coaching'", $html);
        $this->assertStringContainsString('coaching-enabled', $html);
        $this->actingAs($this->user('staff'));
        $html = \Illuminate\Support\Facades\Blade::render($navigation);
        $this->assertStringNotContainsString("activeTab = 'coaching'", $html);
        $this->assertStringNotContainsString('coaching-enabled', $html);
    }

    public function test_coaching_save_rechecks_role_after_page_was_opened(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin);
        $component = Livewire::test(\App\Livewire\Admin\Config\CoachingSettings::class)->set('enabled', false);
        $admin->update(['role' => 'staff']);
        $this->actingAs($admin->fresh());
        $component->call('save')->assertForbidden();
        $this->assertTrue(\App\Services\Coaching\Access::enabled());
    }

    public function test_disabled_setting_stops_management_and_sync_without_deleting_contracts(): void
    {
        $this->actingAs($this->user('admin'));
        $this->contract();
        \App\Models\Setting::setValue('coaching', 'enabled', false);
        Livewire::test(Contracts::class)->assertNotFound();
        $this->artisan('coaching:sync')->expectsOutput('Einzelcoaching-Abgleich ist ausgeschaltet.')->assertSuccessful();
        $this->assertSame(0, app(\App\Services\Coaching\SyncService::class)->import());
        $this->assertSame(0, app(\App\Services\Coaching\SyncService::class)->sendPending());
        $this->assertDatabaseCount('coaching_contracts', 1);
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

    public function test_admin_coaching_email_uses_cbw_base_layout_without_replacing_general_mail_templates(): void
    {
        \App\Models\Setting::setValue('api', 'base_api_url', 'https://schulnetz.example.test');
        $mail = (new \App\Notifications\CoachingNotification(['subject' => 'Einzelcoaching', 'lines' => ['Bitte alle Termine abstimmen.']], 'https://schulnetz.example.test/register', true))->toMail(new \stdClass());
        $html = (string)$mail->view['html'];
        $this->assertStringContainsString('CBW College Berufliche Weiterbildung', $html);
        $this->assertStringContainsString('Im Schulnetz registrieren', $html);
        $this->assertStringContainsString('https://schulnetz.example.test', $html);
        $this->assertStringContainsString('Bitte alle Termine abstimmen.', (string)$mail->view['text']);
        $this->assertStringContainsString('# {{ $greeting }}', file_get_contents(resource_path('views/vendor/notifications/email.blade.php')));
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
        Schema::create('messages', function (Blueprint $t) { $t->id(); $t->string('subject'); $t->text('message'); $t->integer('from_user'); $t->integer('to_user'); $t->integer('status'); $t->timestamps(); });
        \App\Models\Setting::setValue('api', 'base_api_url', 'https://schulnetz.example.test/portal');
        $row = ['id' => 11, 'institut_id' => 1, 'person_id' => '1-900', 'beratung_id' => 'test',
            'tutor_person_id' => $tutor->persons()->first()->person_id, 'title' => 'Einzelcoaching',
            'agreed_minutes' => 180, 'unit_minutes' => 45, 'version' => str_repeat('a',64), 'status' => 'active'];
        app(\App\Services\Coaching\SyncService::class)->importContract($row);
        app(\App\Services\Coaching\SyncService::class)->importContract($row);
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        $this->assertDatabaseCount('messages', 1);
        $message = \App\Models\Message::firstOrFail();
        $this->assertSame($tutor->id, (int)$message->to_user);
        $this->assertStringContainsString('https://schulnetz.example.test/portal/coaching?contract=', $message->message);
        $this->actingAs(User::where('role', 'admin')->first());
        Livewire::test(Contracts::class)->assertSee('Versandvorgänge offen')->assertSee('Gültige Empfänger-E-Mail fehlt.');
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
