<?php

namespace Tests\Feature;

use App\Livewire\Admin\Courses\CourseExportModal;
use App\Livewire\Admin\Courses\CourseList;
use App\Livewire\Admin\Dashboard\CoursesFocus;
use App\Models\Course;
use App\Models\File;
use App\Models\User;
use App\Services\Courses\CourseInvoiceExport;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class LecturerInvoiceExportTest extends TestCase
{
    private bool $invoicePermission = true;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'lecturer_invoice_testing');
        config()->set('database.connections.lecturer_invoice_testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge('lecturer_invoice_testing');
        DB::reconnect('lecturer_invoice_testing');
        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->string('klassen_id')->nullable();
            $t->string('title')->nullable();
            $t->string('type')->default('regular');
            $t->softDeletes();
            $t->timestamps();
            $t->unsignedBigInteger('primary_tutor_person_id')->nullable();
            $t->json('lecturer_plan')->nullable();
            $t->date('planned_start_date')->nullable();
            $t->date('planned_end_date')->nullable();
            $t->boolean('is_active')->default(true);
            $t->string('termin_id')->nullable();
            $t->text('description')->nullable();
            $t->string('room')->nullable();
            $t->json('source_snapshot')->nullable();
        });
        Schema::create('persons', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('vorname')->nullable();
            $t->string('nachname')->nullable();
            $t->softDeletes();
        });
        Schema::create('files', function (Blueprint $t) {
            $t->id();
            $t->string('fileable_type');
            $t->unsignedBigInteger('fileable_id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('name')->nullable();
            $t->string('path');
            $t->string('disk')->nullable();
            $t->string('type');
            $t->string('mime_type')->nullable();
            $t->unsignedBigInteger('size')->nullable();
            $t->unsignedBigInteger('lecturer_person_id')->nullable();
            $t->string('lecturer_contract_id')->nullable();
            $t->string('lecturer_assignment_id')->nullable();
            $t->unsignedInteger('lecturer_plan_revision')->nullable();
            $t->unsignedInteger('document_revision')->nullable();
            $t->unsignedBigInteger('supersedes_file_id')->nullable();
            $t->timestamps();
        });
        Storage::fake('public');
        $user = new User;
        $user->forceFill(['id' => 7, 'name' => 'Verwaltung', 'role' => 'employee']);
        $this->actingAs($user);
        Gate::define('courses.export', fn () => true);
        Gate::define('invoices.view', fn () => $this->invoicePermission);
        Gate::define('courses.ratings.view', fn () => true);
    }

    private function course(string $id = '26T01-1-BMFK'): Course
    {
        return Course::query()->create(['klassen_id' => $id, 'title' => 'DLO1', 'type' => 'regular']);
    }

    private function invoice(Course $course, int $owner, ?int $person, string $contract = '101', int $revision = 1): File
    {
        if ($person) {
            DB::table('persons')->insertOrIgnore(['id' => $person, 'vorname' => 'Dozent', 'nachname' => (string) $person]);
        }
        $path = 'invoices/'.$owner.'-'.$contract.'-'.$revision.'.pdf';
        Storage::disk('public')->put($path, '%PDF-1.4 synthetic '.$owner.' '.$revision);

        return File::withoutEvents(fn () => $course->files()->create([
            'user_id' => $owner, 'name' => 'Rechnung.pdf', 'path' => $path, 'disk' => 'public', 'type' => 'invoice',
            'lecturer_person_id' => $person, 'lecturer_contract_id' => $contract,
            'lecturer_assignment_id' => 'assignment-'.$owner, 'document_revision' => $revision,
        ]));
    }

    public function test_all_owners_and_versions_are_included_with_unique_names_and_originals_remain(): void
    {
        $course = $this->course();
        $a = $this->invoice($course, 11, 101);
        $b = $this->invoice($course, 12, 102, '202');
        $a2 = $this->invoice($course, 11, 101, '101', 2);
        $zipPath = app(CourseInvoiceExport::class)->zip($course);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        $this->assertSame(3, $zip->numFiles);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
            $this->assertStringContainsString('%PDF-1.4 synthetic', $zip->getFromIndex($i));
        }
        $this->assertCount(3, array_unique($names));
        $this->assertStringContainsString('vereinbarung_101', strtolower(implode(' ', $names)));
        $zip->close();
        @unlink($zipPath);
        foreach ([$a, $b, $a2] as $file) {
            Storage::disk('public')->assertExists($file->path);
        }
        $this->assertSame(3, File::query()->count());
    }

    public function test_single_preview_refuses_ambiguous_invoice_selection(): void
    {
        $course = $this->course();
        $a = $this->invoice($course, 11, 101);
        $path = $course->generateInvoicePdfFile();
        $this->assertFileExists($path);
        @unlink($path);
        $this->invoice($course, 12, 102, '202');
        $this->assertNull($course->generateInvoicePdfFile());
        $path = $course->generateInvoicePdfFile($a->id);
        $this->assertFileExists($path);
        @unlink($path);
    }

    public function test_invoice_context_uses_the_existing_course_agreement_reference_without_extra_tables(): void
    {
        $course = $this->course();
        $course->forceFill(['lecturer_plan' => ['status' => 'ready', 'contracts' => [
            ['contract_id' => '101', 'legacy_assignment_uid' => '101', 'reference' => 'Kurszuordnung #101'],
        ], 'assignments' => [
            ['assignment_id' => 'assignment-11', 'person_id' => 101, 'contract_id' => '101', 'legacy_assignment_uid' => '101', 'starts_on' => '2026-10-19', 'ends_on' => '2026-11-06'],
        ]]])->save();
        $invoice = $this->invoice($course, 11, 101);
        $export = app(CourseInvoiceExport::class);
        $this->assertSame('Kurszuordnung #101', $export->context($course, $invoice)['contract']);
        $this->assertStringContainsString('vereinbarung_101', $export->filename($course, $invoice));
        $this->assertFalse(Schema::hasTable('dozenten_vertrag'));
        $this->assertFalse(Schema::hasTable('dozenten_bewertung'));
    }

    public function test_foreign_course_invoice_cannot_be_downloaded_by_id(): void
    {
        $course = $this->course();
        $foreign = $this->invoice($this->course('26T02-1-WIKO'), 12, 102);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CourseInvoiceExport::class)->copyFile($course, $foreign);
    }

    public function test_revoked_invoice_permission_is_checked_when_export_is_executed(): void
    {
        $course = $this->course();
        $this->invoice($course, 11, 101);
        $this->assertCount(1, app(CourseInvoiceExport::class)->files($course));
        $this->invoicePermission = false;
        $this->expectException(AuthorizationException::class);
        $course->exportInvoicePdf();
    }

    public function test_livewire_export_cannot_enable_invoice_option_after_permission_revocation(): void
    {
        $modal = new CourseExportModal;
        $modal->courseIds = [1];
        $modal->includeTutorData = true;
        $this->invoicePermission = false;
        $this->expectException(AuthorizationException::class);
        $modal->export();
    }

    public function test_legacy_invoice_has_no_invented_lecturer_owner(): void
    {
        $course = $this->course();
        $file = $this->invoice($course, 11, null);
        $export = app(CourseInvoiceExport::class);
        $this->assertSame('Zuordnung offen', $export->personLabel($file));
        $this->assertStringContainsString('bestandsbeleg', $export->filename($course, $file));
    }

    public function test_legacy_invoice_can_show_a_proven_unique_account_owner_without_assigning_an_employment(): void
    {
        $course = $this->course();
        $file = $this->invoice($course, 11, null);
        DB::table('persons')->insert(['id' => 101, 'user_id' => 11, 'vorname' => 'Früherer', 'nachname' => 'Dozent']);
        $export = app(CourseInvoiceExport::class);
        $this->assertSame('Früherer Dozent', $export->personLabel($file));
        $this->assertNull($file->fresh()->lecturer_person_id);
        DB::table('persons')->insert(['id' => 102, 'user_id' => 11, 'vorname' => 'Zweiter', 'nachname' => 'Bezug']);
        $this->assertSame('Zuordnung offen', $export->personLabel($file));
    }

    public function test_course_complete_export_contains_all_invoice_versions(): void
    {
        $course = $this->course();
        $this->invoice($course, 11, 101);
        $this->invoice($course, 12, 102, '202');
        $path = $course->generateExportAllZipFile([
            'includeDocumentation' => false, 'includeRedThread' => false, 'includeParticipants' => false,
            'includeAttendance' => false, 'includeExamResults' => false, 'includeCourseRatings' => false, 'includeTutorData' => true,
        ]);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertSame(2, $zip->numFiles);
        $this->assertStringStartsWith('Dozentenrechnungen/', $zip->getNameIndex(0));
        $zip->close();
        @unlink($path);
    }

    public function test_multiple_lecturer_status_does_not_claim_completeness_from_one_owner_or_extra_versions(): void
    {
        $course = $this->course();
        $course->forceFill(['lecturer_plan' => ['status' => 'ready', 'assignments' => [
            ['assignment_id' => 'assignment-11', 'person_id' => 101, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-31'],
            ['assignment_id' => 'assignment-12', 'person_id' => 102, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-06'],
        ]]])->save();
        $this->assertSame(0, $course->invoiceState());
        $this->invoice($course, 11, 101);
        $this->invoice($course, 11, 101, '101', 2);
        $this->assertSame(2, $course->invoiceState());
        $this->invoice($course, 12, 102, '202');
        $this->assertSame(1, $course->invoiceState());
    }

    public function test_admin_current_tutor_switches_at_midnight_even_with_stale_primary_relation(): void
    {
        DB::table('persons')->insert([
            ['id' => 101, 'vorname' => 'Alter', 'nachname' => 'Dozent'],
            ['id' => 102, 'vorname' => 'Neuer', 'nachname' => 'Dozent'],
        ]);
        $course = $this->course();
        $course->forceFill(['primary_tutor_person_id' => 101, 'planned_start_date' => '2026-10-19', 'planned_end_date' => '2026-11-06', 'lecturer_plan' => [
            'status' => 'ready', 'person_ids' => [101, 102], 'assignments' => [
                ['assignment_id' => 'a', 'person_id' => 101, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-31'],
                ['assignment_id' => 'b', 'person_id' => 102, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-06'],
            ],
        ]])->save();
        $course->load('tutor');
        try {
            Carbon::setTestNow(Carbon::parse('2026-10-31 23:59:59', 'Europe/Berlin'));
            $this->assertSame(101, $course->tutor->id);
            Carbon::setTestNow(Carbon::parse('2026-11-01 00:00:00', 'Europe/Berlin'));
            $this->assertSame(102, $course->tutor->id);
            $this->assertSame(101, $course->primary_tutor_person_id);
            $dashboard = new CoursesFocus;
            $dashboard->loadCourses();
            $this->assertSame('Neuer Dozent', $dashboard->courses[0]['tutor_name']);
            DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$parts) => implode('', $parts));
            $list = new class extends CourseList
            {
                public function foundIds(): array
                {
                    return $this->baseQuery()->pluck('courses.id')->all();
                }
            };
            $list->search = 'Neuer Dozent';
            $this->assertSame([$course->id], $list->foundIds());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_history_stays_hidden_for_one_person_and_reasons_require_explicit_admin_request(): void
    {
        $row = ['assignment_id' => 'a', 'name' => 'Alter Dozent', 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-31', 'reason' => 'Interner Grund'];
        $display = ['multiple' => false, 'current' => $row, 'previous' => [], 'planned' => [], 'assignments' => [$row]];
        $html = Blade::render('<x-courses.lecturer-history :display="$display" :show-reasons="true" />', compact('display'));
        $this->assertStringNotContainsString('Hauptdozent', $html);
        $this->assertStringNotContainsString('Interner Grund', $html);
        $display['multiple'] = true;
        $html = Blade::render('<x-courses.lecturer-history :display="$display" />', compact('display'));
        $this->assertStringNotContainsString('Interner Grund', $html);
        $html = Blade::render('<x-courses.lecturer-history :display="$display" :show-reasons="true" />', compact('display'));
        $this->assertStringContainsString('Interner Grund', $html);
    }

    public function test_documentation_export_uses_row_author_and_marks_unknown_legacy_authorship(): void
    {
        $date = Carbon::parse('2026-10-21');
        $meta = ['date_from' => $date, 'date_to' => $date, 'tutor_name' => 'Neuer Hauptdozent', 'has_lecturer_plan' => true];
        $row = ['index' => 1, 'date' => $date, 'time_range' => '08:00-12:00', 'notes_html' => '<p>Bestandsinhalt</p>', 'tutor_name' => 'Alter Unterzeichner'];
        $html = view('pdf.courses.documentation', ['meta' => $meta, 'rows' => [$row], 'footer' => []])->render();
        $this->assertStringContainsString('Alter Unterzeichner', $html);
        $row['tutor_name'] = null;
        $html = view('pdf.courses.documentation', ['meta' => $meta, 'rows' => [$row], 'footer' => []])->render();
        $this->assertStringContainsString('Zuordnung ungeklärt', $html);
    }

    public function test_private_base_invoice_resolution_uses_its_file_id_and_keeps_the_original(): void
    {
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key');
            $t->text('value');
        });
        DB::table('settings')->insert([
            ['key' => 'base_api_url', 'value' => json_encode('https://base.example.test')],
            ['key' => 'base_api_key', 'value' => json_encode('synthetic-key')],
        ]);
        Http::preventStrayRequests();
        Http::fake(['base.example.test/*' => Http::response(['success' => true, 'url' => 'https://base.example.test/temp/proven.pdf'])]);
        $course = $this->course();
        $course->forceFill(['lecturer_plan' => ['status' => 'ready', 'assignments' => [
            ['assignment_id' => 'assignment-11', 'person_id' => 101, 'starts_on' => '2026-10-19', 'ends_on' => '2026-11-06'],
        ]]])->save();
        $file = $this->invoice($course, 11, 101);
        Storage::disk('public')->delete($file->path);
        $this->assertSame('https://base.example.test/temp/proven.pdf', $file->getEphemeralPublicUrl());
        Http::assertSent(fn ($request) => $request['file_id'] === $file->id && ! isset($request['url']) && $request->hasHeader('X-API-KEY', 'synthetic-key'));
        try {
            $file->delete();
            $this->fail('Invoice evidence unexpectedly deleted.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        $this->assertNotNull($file->fresh());
        Http::assertSentCount(1);
    }

    public function test_generic_file_preview_rechecks_invoice_permission_before_media_io(): void
    {
        $course = $this->course();
        $course->forceFill(['lecturer_plan' => ['status' => 'ready', 'assignments' => []]])->save();
        $file = $this->invoice($course, 11, 101);
        $this->invoicePermission = false;
        Http::fake();
        try {
            $file->getEphemeralPublicUrl();
            $this->fail('Invoice unexpectedly exposed.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        Http::assertNothingSent();
    }

    public function test_native_admin_lecturer_card_preserves_single_case_and_marks_history_only_for_multiple_people(): void
    {
        DB::table('persons')->insert([
            ['id' => 101, 'vorname' => 'Anna', 'nachname' => 'Beispiel'],
            ['id' => 102, 'vorname' => 'Bernd', 'nachname' => 'Muster'],
        ]);
        $course = $this->course();
        $course->forceFill(['primary_tutor_person_id' => 101])->save();
        $source = file_get_contents(resource_path('views/livewire/admin/courses/course-show.blade.php'));
        $start = strpos($source, '            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">');
        $end = strpos($source, '            <div class="mt-4 grid grid-cols-2 gap-3 text-xs">', $start);
        $card = str_replace('$this->lecturerDisplay', '$lecturerDisplay', substr($source, $start, $end - $start));
        $lecturerDisplay = \App\Services\Courses\CourseLecturerAccess::display($course);
        $single = Blade::render($card, compact('course', 'lecturerDisplay'));
        $this->assertStringContainsString('Tutor', $single);
        $this->assertStringNotContainsString('Hauptdozent', $single);
        $this->saveUiPreview('admin-ein-dozent-unveraendert', $single);
        $course->forceFill(['lecturer_plan' => ['status' => 'ready', 'assignments' => [
            ['assignment_id' => 'a', 'person_id' => 101, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-31'],
            ['assignment_id' => 'b', 'person_id' => 102, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-06', 'reason' => 'Terminplanung angepasst'],
        ]]])->save();
        try {
            Carbon::setTestNow(Carbon::parse('2026-11-03 10:00', 'Europe/Berlin'));
            $lecturerDisplay = \App\Services\Courses\CourseLecturerAccess::display($course);
            $multiple = Blade::render($card, compact('course', 'lecturerDisplay'));
            $this->assertStringContainsString('Hauptdozent', $multiple);
            $this->assertStringContainsString('früherer Hauptdozent', $multiple);
            $this->assertStringContainsString('Terminplanung angepasst', $multiple);
            $this->saveUiPreview('admin-aktueller-und-frueherer-dozent', $multiple);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function saveUiPreview(string $name, string $html): void
    {
        $directory = getenv('CBW_LECTURER_UI_DIR');
        if (! $directory) {
            return;
        }
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $css = file_get_contents(public_path('build/css/tailwind.min.css'));
        $avatar = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" rx="32" fill="#e0f2fe"/><text x="32" y="39" text-anchor="middle" font-family="Arial" font-size="22" fill="#075985">D</text></svg>');
        $html = preg_replace('/src="https:\/\/ui-avatars.com[^\"]*"/', 'src="'.$avatar.'"', $html);
        file_put_contents($directory.'/'.$name.'.html', '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin-Schulnetz Originalkarte – lokale Beispieldaten</title><style>'.$css.'</style><style>body{display:block!important;width:100%!important;min-width:0!important;box-sizing:border-box!important;margin:0!important;padding:32px!important;background:#f8fafc;font-family:Arial,sans-serif!important}main{max-width:760px;margin:auto;background:white;padding:24px;border:1px solid #e2e8f0;border-radius:12px}</style><main style="display:block!important;width:calc(100vw - 64px)!important;max-width:1100px!important;min-width:0!important;box-sizing:border-box!important;margin:0 auto!important;padding:24px!important;font-family:Arial,sans-serif!important"><p style="font-size:12px;color:#64748b;margin-bottom:20px">Lokale Original-Blades · synthetische Daten · keine LIVE-Abnahme</p>'.$html.'</main></html>');
    }
}
