<?php

namespace Tests\Unit;

use App\Livewire\Admin\Courses\AttendanceEditorModal;
use App\Livewire\Admin\UserProfile\UserCourses;
use App\Models\Course;
use App\Models\CourseDay;
use App\Models\Person;
use App\Models\User;
use App\Services\ApiUvs\ApiUvsService;
use App\Services\ApiUvs\CourseApiServices\CourseDayAttendanceSyncService;
use App\Services\ApiUvs\CourseApiServices\CourseUvsDirectLoadService;
use App\Support\Rbac\RbacCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Mockery;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CourseVisibilityAndAttendanceTest extends TestCase
{
    public function test_course_scope_excludes_holidays_without_excluding_null_types(): void
    {
        $query = Course::withoutHolidays();

        $this->assertStringContainsString('courses`.`type', $query->toSql());
        $this->assertContains('ferien', $query->getBindings());
    }

    public function test_attendance_edit_permission_is_part_of_rbac_catalog(): void
    {
        $this->assertContains('courses.attendance.edit_today', RbacCatalog::allPermissions());

        $permission = collect(RbacCatalog::permissionGroups()['Kursverwaltung'])
            ->firstWhere('key', 'courses.attendance.edit_today');

        $this->assertTrue((bool) ($permission['admin_only'] ?? false));
    }

    public function test_non_admin_cannot_open_attendance_editor_server_side(): void
    {
        $user = new User();
        $user->role = 'employee';
        Auth::setUser($user);

        $method = new ReflectionMethod(AttendanceEditorModal::class, 'editableDay');
        $method->setAccessible(true);

        try {
            $method->invoke(new AttendanceEditorModal(), 1);
            $this->fail('Nicht-Admins dürfen den Anwesenheitseditor nicht öffnen.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_attendance_modal_accepts_scalar_and_livewire_array_day_ids_without_model_binding(): void
    {
        $component = new AttendanceEditorModal();
        $method = new ReflectionMethod($component, 'normalizeCourseDayId');
        $method->setAccessible(true);
        $openMethod = new ReflectionMethod($component, 'open');

        $this->assertNull($openMethod->getParameters()[0]->getType());
        $this->assertSame(42, $method->invoke($component, 42));
        $this->assertSame(42, $method->invoke($component, ['courseDayId' => 42]));
        $this->assertSame(42, $method->invoke($component, [['courseDayId' => 42]]));
        $this->assertNull($method->invoke($component, ['invalid' => 1, 'other' => 2]));
    }

    public function test_attendance_modal_rows_loader_does_not_collide_with_livewire_hydrate_hook(): void
    {
        $this->assertFalse(method_exists(AttendanceEditorModal::class, 'hydrateRows'));
        $this->assertTrue(method_exists(AttendanceEditorModal::class, 'loadRowsFromDay'));

        Livewire::test(AttendanceEditorModal::class)
            ->set('syncError', 'Hydration erfolgreich')
            ->assertSet('syncError', 'Hydration erfolgreich');
    }

    public function test_admin_attendance_stats_match_the_tutor_status_groups(): void
    {
        $component = new AttendanceEditorModal();
        $method = new ReflectionMethod($component, 'calculateStats');
        $method->setAccessible(true);

        $stats = $method->invoke($component, [
            ['has_entry' => true, 'present' => true, 'excused' => false, 'late_minutes' => 0],
            ['has_entry' => true, 'present' => true, 'excused' => false, 'late_minutes' => 15],
            ['has_entry' => true, 'present' => false, 'excused' => true, 'late_minutes' => 0],
            ['has_entry' => true, 'present' => false, 'excused' => false, 'late_minutes' => 0],
            ['has_entry' => false, 'present' => false, 'excused' => false, 'late_minutes' => 0],
        ]);

        $this->assertSame([
            'present' => 1,
            'late' => 1,
            'excused' => 1,
            'absent' => 1,
            'unknown' => 1,
            'total' => 5,
        ], $stats);
    }

    public function test_admin_attendance_derives_badge_status_from_entered_times(): void
    {
        $component = new AttendanceEditorModal();
        $method = new ReflectionMethod($component, 'rowTiming');
        $method->setAccessible(true);
        $day = new CourseDay([
            'date' => '2026-07-19',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        [$arrival, $leave, $late, $early] = $method->invoke($component, $day, [
            'arrived_at' => '08:15',
            'left_at' => '15:30',
            'late_minutes' => 0,
            'left_early_minutes' => 0,
        ]);

        $this->assertSame('08:15', $arrival);
        $this->assertSame('15:30', $leave);
        $this->assertSame(15, $late);
        $this->assertSame(30, $early);
    }

    public function test_admin_attendance_tutor_style_row_renders_with_auto_save_controls(): void
    {
        Livewire::test(AttendanceEditorModal::class)
            ->set('plannedStart', '08:00')
            ->set('plannedEnd', '16:00')
            ->set('arrivalInput', [42 => '08:15'])
            ->set('leaveInput', [42 => '15:30'])
            ->set('noteInput', [42 => 'Testnotiz'])
            ->set('rows', [[
                'id' => 42,
                'name' => 'Mustermann, Erika',
                'teilnehmer_id' => 'TN-42',
                'has_entry' => true,
                'present' => true,
                'excused' => false,
                'late_minutes' => 15,
                'left_early_minutes' => 30,
                'arrived_at' => '08:15',
                'left_at' => '15:30',
                'note' => 'Testnotiz',
                'state' => 'synced',
            ]])
            ->assertSee('Mustermann, Erika')
            ->assertSee('Gekommen (Uhrzeit)')
            ->assertSee('Gegangen (Uhrzeit)')
            ->assertSee('Notiz');
    }

    public function test_vacation_uses_immediately_preceding_non_vacation_block(): void
    {
        $person = new Person();
        $person->programdata = [
            'teilnehmer_id' => 'TN-100',
            'tn_baust' => [
                [
                    'tn_baustein_id' => 'B-1',
                    'kurzbez' => 'MOD1',
                    'beginn_baustein' => '2026/01/05',
                    'ende_baustein' => '2026/01/30',
                    'klassen_id' => 'KLASSE-1',
                ],
                [
                    'tn_baustein_id' => 'F-1',
                    'kurzbez' => 'FERI',
                    'beginn_baustein' => '2026/02/02',
                    'ende_baustein' => '2026/02/06',
                    'klassen_id' => null,
                ],
                [
                    'tn_baustein_id' => 'B-2',
                    'kurzbez' => 'MOD2',
                    'beginn_baustein' => '2026/02/09',
                    'ende_baustein' => '2026/03/06',
                    'klassen_id' => 'KLASSE-2',
                ],
                [
                    'tn_baustein_id' => 'F-2',
                    'kurzbez' => 'FERI',
                    'beginn_baustein' => '2026/03/09',
                    'ende_baustein' => '2026/03/13',
                    'klassen_id' => null,
                ],
            ],
        ];

        $vacation = new Course();
        $vacation->forceFill([
            'type' => 'ferien',
            'planned_start_date' => '2026-03-09',
            'planned_end_date' => '2026-03-13',
            '_enrollment_tn_baustein_id' => 'F-2',
            '_enrollment_teilnehmer_id' => 'TN-100',
        ]);

        $method = new ReflectionMethod(UserCourses::class, 'vacationPredecessorClassId');
        $method->setAccessible(true);

        $this->assertSame('KLASSE-2', $method->invoke(new UserCourses(), $vacation, $person));
    }

    public function test_vacation_without_synchronized_predecessor_class_id_stays_hidden(): void
    {
        $person = new Person();
        $person->programdata = [
            'teilnehmer_id' => 'TN-100',
            'tn_baust' => [
                [
                    'tn_baustein_id' => 'B-1',
                    'kurzbez' => 'MOD1',
                    'beginn_baustein' => '2026/01/05',
                    'ende_baustein' => '2026/01/30',
                    'klassen_id' => null,
                ],
                [
                    'tn_baustein_id' => 'F-1',
                    'kurzbez' => 'FERI',
                    'beginn_baustein' => '2026/02/02',
                    'ende_baustein' => '2026/02/06',
                ],
            ],
        ];

        $vacation = new Course();
        $vacation->forceFill([
            'type' => 'ferien',
            'planned_start_date' => '2026-02-02',
            'planned_end_date' => '2026-02-06',
            '_enrollment_tn_baustein_id' => 'F-1',
            '_enrollment_teilnehmer_id' => 'TN-100',
        ]);

        $method = new ReflectionMethod(UserCourses::class, 'vacationPredecessorClassId');
        $method->setAccessible(true);

        $this->assertNull($method->invoke(new UserCourses(), $vacation, $person));
    }

    public function test_admin_sync_mapping_uses_existing_uvs_payload_contract(): void
    {
        $person = new Person();
        $person->forceFill([
            'id' => 7,
            'teilnehmer_id' => 'TN-7',
            'institut_id' => 5,
        ]);

        $course = new Course();
        $course->forceFill([
            'id' => 11,
            'termin_id' => 'TERM-11',
            'institut_id' => 5,
        ]);
        $course->setRelation('participants', collect([$person]));
        $course->setRelation('tutor', null);

        $day = new CourseDay();
        $day->forceFill([
            'id' => 21,
            'course_id' => 11,
            'date' => Carbon::parse('2026-07-19'),
            'start_time' => '08:00',
            'end_time' => '16:00',
            'std' => 8,
            'attendance_data' => [
                'participants' => [
                    7 => [
                        'present' => true,
                        'excused' => false,
                        'late_minutes' => 15,
                        'left_early_minutes' => 30,
                        'arrived_at' => '08:15',
                        'left_at' => '15:30',
                        'note' => 'Test',
                        'state' => 'dirty',
                    ],
                ],
            ],
        ]);
        $day->setRelation('course', $course);

        $service = new CourseDayAttendanceSyncService(
            Mockery::mock(CourseUvsDirectLoadService::class),
            Mockery::mock(ApiUvsService::class)
        );
        $method = new ReflectionMethod($service, 'mapChanges');
        $method->setAccessible(true);

        [$changes, $localIds] = $method->invoke($service, $day, [7]);

        $this->assertSame([7], $localIds);
        $this->assertCount(1, $changes);
        $this->assertSame('update', $changes[0]['action']);
        $this->assertSame('TA', $changes[0]['fehl_grund']);
        $this->assertSame('08:15', $changes[0]['gekommen']);
        $this->assertSame('15:30', $changes[0]['gegangen']);
        $this->assertSame(0.75, $changes[0]['fehl_std']);
        $this->assertSame('TN-7-TERM-11', $changes[0]['tn_fehltage_id']);

        $day->attendance_data = [
            'participants' => [
                7 => [
                    'present' => true,
                    'excused' => false,
                    'late_minutes' => 0,
                    'left_early_minutes' => 0,
                    'src_api_id' => 'REMOTE-7',
                ],
            ],
        ];

        [$presentChanges, $presentLocalIds, $fullyPresentLocalIds] = $method->invoke($service, $day, [7]);

        $this->assertSame([7], $presentLocalIds);
        $this->assertSame([7], $fullyPresentLocalIds);
        $this->assertSame('delete', $presentChanges[0]['action']);
    }

    public function test_admin_row_sync_calls_uvs_for_fully_present_participant_without_remote_absence(): void
    {
        $person = new Person();
        $person->forceFill(['id' => 7, 'teilnehmer_id' => 'TN-7', 'institut_id' => 5]);

        $course = new Course();
        $course->forceFill(['id' => 11, 'termin_id' => 'TERM-11', 'institut_id' => 5]);
        $course->setRelation('participants', collect([$person]));
        $course->setRelation('tutor', null);

        $day = Mockery::mock(CourseDay::class)->makePartial();
        $day->forceFill([
            'id' => 21,
            'course_id' => 11,
            'date' => Carbon::parse('2026-07-19'),
            'start_time' => '08:00',
            'end_time' => '16:00',
            'std' => 8,
            'attendance_data' => [
                'participants' => [
                    7 => [
                        'present' => true,
                        'excused' => false,
                        'late_minutes' => 0,
                        'left_early_minutes' => 0,
                        'src_api_id' => null,
                        'state' => 'synced',
                    ],
                ],
            ],
        ]);
        $day->setRelation('course', $course);
        $day->shouldReceive('loadMissing')->once()->andReturnSelf();
        $day->shouldReceive('saveQuietly')->once()->andReturnTrue();

        $api = Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')
            ->once()
            ->with('POST', '/api/course/courseday/syncattendancedata', [
                'termin_id' => 'TERM-11',
                'date' => '2026-07-19',
                'teilnehmer_ids' => ['TN-7'],
                'changes' => [],
            ])
            ->andReturn(['ok' => true]);

        $loader = Mockery::mock(CourseUvsDirectLoadService::class);
        $loader->shouldReceive('loadAttendanceForDay')->once()->with($day)->andReturnTrue();

        $service = new CourseDayAttendanceSyncService($loader, $api);

        $this->assertTrue($service->syncToRemote($day, [7]));
        $this->assertSame(
            CourseDayAttendanceSyncService::STATE_SYNCED,
            data_get($day->attendance_data, 'participants.7.state')
        );
    }

    public function test_changed_admin_attendance_views_compile(): void
    {
        foreach ([
            'livewire/admin/courses/attendance-editor-modal.blade.php',
            'livewire/admin/courses/course-days-panel.blade.php',
            'livewire/admin/courses/course-show.blade.php',
            'livewire/admin/employees/team-rbac-modal.blade.php',
        ] as $view) {
            $compiled = app('blade.compiler')->compileString(
                file_get_contents(resource_path('views/'.$view))
            );

            $this->assertNotSame('', trim($compiled), $view);
        }

        $panelSource = file_get_contents(resource_path('views/livewire/admin/courses/course-days-panel.blade.php'));
        $modalSource = file_get_contents(resource_path('views/livewire/admin/courses/attendance-editor-modal.blade.php'));
        $modalComponentSource = file_get_contents(app_path('Livewire/Admin/Courses/AttendanceEditorModal.php'));
        $attendanceSyncSource = file_get_contents(app_path('Services/ApiUvs/CourseApiServices/CourseDayAttendanceSyncService.php'));
        $apiServiceSource = file_get_contents(app_path('Services/ApiUvs/ApiUvsService.php'));

        $this->assertStringContainsString('wire:click="openAttendanceEditor', $panelSource);
        $this->assertStringNotContainsString('attendance_rows', $panelSource);
        $this->assertStringContainsString('fad fa-play-circle', $modalSource);
        $this->assertStringContainsString('fad fa-flag-checkered', $modalSource);
        $this->assertStringContainsString('w-64 flex-col', $modalSource);
        $this->assertStringContainsString('border-t border-slate-300', $modalSource);
        $this->assertStringContainsString('Gekommen um', $modalSource);
        $this->assertStringContainsString('Gegangen um', $modalSource);
        $this->assertStringNotContainsString('Keine Zusatzangabe', $modalSource);
        $this->assertStringContainsString('wire:change="saveArrival', $modalSource);
        $this->assertStringContainsString('wire:change="saveLeave', $modalSource);
        $this->assertStringContainsString('wire:change="saveNote', $modalSource);
        $this->assertStringNotContainsString('saving: false', $modalSource);
        $this->assertStringContainsString('wire:target="clearTimes', $modalSource);
        $this->assertStringContainsString('fad fa-spinner-third fa-spin', $modalSource);
        $this->assertStringContainsString('wire:loading.class.remove="hidden"', $modalSource);
        $this->assertSame(1, substr_count($modalSource, 'wire:loading.class.remove="hidden"'));
        $this->assertStringContainsString('<x-user.public-info :person="$row[\'person\']" />', $modalSource);
        $this->assertStringContainsString('attendance-time-input', $modalSource);
        $this->assertStringContainsString('attendance-time-select', $modalSource);
        $this->assertStringContainsString("auth()->user()?->isAdmin()", $modalSource);
        $this->assertStringNotContainsString('Admin-Bearbeitung', $modalSource);
        $this->assertStringContainsString('Bitte wählen', $modalSource);
        $this->assertStringNotContainsString('wire:click="saveTimes', $modalSource);
        $this->assertStringNotContainsString('Anwesenheiten werden verarbeitet', $modalSource);
        $this->assertStringNotContainsString('Nur heute bearbeitbar', $modalSource);
        $this->assertStringNotContainsString("->whereDate('date'", $modalComponentSource);
        $this->assertStringContainsString('loadFromRemote($day)', $modalComponentSource);
        $this->assertStringContainsString('function saveArrival(', $modalComponentSource);
        $this->assertStringContainsString('function saveLeave(', $modalComponentSource);
        $this->assertStringContainsString('function clearTimes(', $modalComponentSource);
        $this->assertStringContainsString('DB::transaction(', $modalComponentSource);
        $this->assertStringNotContainsString('STATE_DIRTY', $modalComponentSource);
        $this->assertStringNotContainsString('vorgemerkt', $modalComponentSource);
        $this->assertStringContainsString("request('POST', self::ENDPOINT_SYNC", $attendanceSyncSource);
        $this->assertStringNotContainsString('if (empty($changes))', $attendanceSyncSource);
        $this->assertStringContainsString("['state'] = self::STATE_SYNCED", $attendanceSyncSource);
        $this->assertStringContainsString("'Connection'    => 'close'", $apiServiceSource);
        $this->assertStringContainsString('CURLOPT_FRESH_CONNECT => true', $apiServiceSource);
        $this->assertStringContainsString('public function request(', $apiServiceSource);
    }
}
