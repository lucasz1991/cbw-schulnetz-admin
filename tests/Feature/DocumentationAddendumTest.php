<?php

namespace Tests\Feature;

use App\Livewire\Admin\Courses\DocumentationAddendumEditorModal;
use App\Livewire\Admin\Employees\TeamRbacModal;
use App\Models\CourseDay;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac\RbacCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentationAddendumTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'documentation_addendum_testing');
        config()->set('database.connections.documentation_addendum_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('documentation_addendum_testing');
        DB::reconnect('documentation_addendum_testing');

        $this->createSchema();
    }

    public function test_permission_is_available_for_teams_and_disabled_by_default(): void
    {
        $permission = collect(RbacCatalog::permissionGroups()['Kursverwaltung'])
            ->firstWhere('key', 'courses.documentation_addendum.edit');

        $this->assertNotNull($permission);
        $this->assertSame('Dokumentationszusatz freigeben', $permission['label']);
        $this->assertFalse((bool) ($permission['admin_only'] ?? false));
        $this->assertFalse(RbacCatalog::defaultTeamPermissions()['courses.documentation_addendum.edit']);
        $this->assertNotContains('courses.documentation_addendum.edit', RbacCatalog::adminOnlyPermissions());
    }

    public function test_authorized_team_member_can_save_publish_and_clear_an_addendum_without_changing_original_notes(): void
    {
        [$user, $courseDayId] = $this->createAuthorizedCourseDay();
        $this->actingAs($user);

        Livewire::test(DocumentationAddendumEditorModal::class)
            ->call('open', $courseDayId)
            ->assertSet('originalNotesHtml', '<p>Original</p>')
            ->set('documentationAddendum', '<p>Veröffentlichter Zusatz</p>')
            ->set('documentationAddendumStatus', CourseDay::DOCUMENTATION_ADDENDUM_STATUS_PUBLISHED)
            ->call('save')
            ->assertSet('documentationAddendumStatus', CourseDay::DOCUMENTATION_ADDENDUM_STATUS_PUBLISHED);

        $day = CourseDay::findOrFail($courseDayId);
        $this->assertSame('<p>Original</p>', $day->notes);
        $this->assertSame(2, (int) $day->note_status);
        $this->assertSame('<p>Veröffentlichter Zusatz</p>', $day->documentation_addendum);
        $this->assertSame(CourseDay::DOCUMENTATION_ADDENDUM_STATUS_PUBLISHED, $day->documentation_addendum_status);
        $this->assertSame($user->id, $day->documentation_addendum_saved_by_user_id);
        $this->assertNotNull($day->documentation_addendum_saved_at);

        Livewire::test(DocumentationAddendumEditorModal::class)
            ->call('open', $courseDayId)
            ->set('documentationAddendum', '<p>&nbsp;</p>')
            ->set('documentationAddendumStatus', CourseDay::DOCUMENTATION_ADDENDUM_STATUS_PUBLISHED)
            ->call('save')
            ->assertSet('documentationAddendum', '')
            ->assertSet('documentationAddendumStatus', CourseDay::DOCUMENTATION_ADDENDUM_STATUS_DRAFT);

        $clearedDay = CourseDay::findOrFail($courseDayId);
        $this->assertNull($clearedDay->documentation_addendum);
        $this->assertSame(CourseDay::DOCUMENTATION_ADDENDUM_STATUS_DRAFT, $clearedDay->documentation_addendum_status);
        $this->assertSame('<p>Original</p>', $clearedDay->notes);
        $this->assertSame(2, (int) $clearedDay->note_status);
    }

    public function test_team_member_without_permission_cannot_open_editor(): void
    {
        [$user, $courseDayId, $team] = $this->createAuthorizedCourseDay();
        $team->forceFill(['rbac_permissions' => ['courses.documentation_addendum.edit' => false]])->save();
        $user->unsetRelation('currentTeam');
        $this->actingAs($user->fresh());

        Livewire::test(DocumentationAddendumEditorModal::class)
            ->call('open', $courseDayId)
            ->assertForbidden();
    }

    public function test_non_admin_role_manager_can_see_and_change_all_team_permissions(): void
    {
        DB::table('teams')->insert([
            'id' => 1,
            'name' => 'Standardteam',
            'personal_team' => false,
            'rbac_permissions' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $team = Team::query()->create([
            'name' => 'Bearbeitungsteam',
            'personal_team' => false,
            'rbac_permissions' => [
                'roles.manage' => true,
                'courses.view' => true,
                'courses.attendance.edit_today' => true,
                'courses.documentation_addendum.edit' => true,
            ],
        ]);

        $user = User::query()->create([
            'name' => 'Rollenmanager',
            'email' => 'rollenmanager@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'status' => true,
        ]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($user->fresh());

        $component = Livewire::test(TeamRbacModal::class)->call('open');
        $matrix = $component->get('matrix');
        $teamMatrix = $matrix[(string) $team->id] ?? [];

        $this->assertTrue($teamMatrix['courses__dot__documentation_addendum__dot__edit']);
        $this->assertTrue($teamMatrix['courses__dot__attendance__dot__edit_today']);

        $component->call('setSelectedTeamToFalse')->call('save');

        $permissions = $team->fresh()->rbac_permissions;
        $this->assertFalse($permissions['courses.documentation_addendum.edit']);
        $this->assertFalse($permissions['courses.attendance.edit_today']);
        $this->assertFalse($permissions['roles.manage']);
        $this->assertFalse($permissions['courses.view']);
    }

    public function test_admin_views_and_pdf_contract_compile_with_addendum_support(): void
    {
        foreach ([
            'components/ui/editor/toast.blade.php',
            'livewire/admin/courses/documentation-addendum-editor-modal.blade.php',
            'livewire/admin/courses/course-days-panel.blade.php',
            'livewire/admin/courses/course-show.blade.php',
            'pdf/courses/documentation.blade.php',
        ] as $view) {
            $compiled = app('blade.compiler')->compileString(
                file_get_contents(resource_path('views/'.$view))
            );

            $this->assertNotSame('', trim($compiled), $view);
        }

        $panel = file_get_contents(resource_path('views/livewire/admin/courses/course-days-panel.blade.php'));
        $modal = file_get_contents(resource_path('views/livewire/admin/courses/documentation-addendum-editor-modal.blade.php'));
        $pdf = file_get_contents(resource_path('views/pdf/courses/documentation.blade.php'));
        $courseModel = file_get_contents(app_path('Models/Course.php'));

        $this->assertStringContainsString("@can('courses.documentation_addendum.edit')", $panel);
        $this->assertStringContainsString('Original-Dokumentation', $modal);
        $this->assertStringContainsString('Schreibgeschützt', $modal);
        $this->assertStringContainsString('documentation_addendum_html', $pdf);
        $this->assertStringContainsString('publishedDocumentationAddendumHtml()', $courseModel);
    }

    /**
     * @return array{0: User, 1: int, 2: Team}
     */
    protected function createAuthorizedCourseDay(): array
    {
        $team = Team::query()->create([
            'name' => 'Doku-Team',
            'personal_team' => false,
            'rbac_permissions' => [
                'courses.documentation_addendum.edit' => true,
            ],
        ]);

        $user = User::query()->create([
            'name' => 'Doku Bearbeiter',
            'email' => 'doku@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'status' => true,
        ]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $courseId = DB::table('courses')->insertGetId([
            'title' => 'Testbaustein',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courseDayId = DB::table('course_days')->insertGetId([
            'course_id' => $courseId,
            'date' => '2026-07-22',
            'notes' => '<p>Original</p>',
            'note_status' => 2,
            'documentation_addendum_status' => CourseDay::DOCUMENTATION_ADDENDUM_STATUS_DRAFT,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user->fresh(), $courseDayId, $team];
    }

    protected function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->default('employee');
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('current_team_id')->nullable();
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

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('course_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->date('date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('day_sessions')->nullable();
            $table->text('attendance_data')->nullable();
            $table->string('topic')->nullable();
            $table->longText('notes')->nullable();
            $table->unsignedTinyInteger('note_status')->default(0);
            $table->longText('documentation_addendum')->nullable();
            $table->unsignedTinyInteger('documentation_addendum_status')->default(0);
            $table->unsignedBigInteger('documentation_addendum_saved_by_user_id')->nullable();
            $table->timestamp('documentation_addendum_saved_at')->nullable();
            $table->text('settings')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });
    }
}
