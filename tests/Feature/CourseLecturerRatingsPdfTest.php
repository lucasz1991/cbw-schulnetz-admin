<?php

namespace Tests\Feature;

use App\Models\Course;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CourseLecturerRatingsPdfTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
            $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'queue.default' => 'sync', 'session.driver' => 'array', 'mail.default' => 'array']);
        });
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::purge('sqlite');
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-07 10:00', 'Europe/Berlin'));
        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('klassen_id');
            $t->string('termin_id')->nullable();
            $t->string('type')->default('regular');
            $t->integer('primary_tutor_person_id')->nullable();
            $t->date('planned_start_date')->nullable();
            $t->date('planned_end_date')->nullable();
            $t->json('source_snapshot')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('persons', function (Blueprint $t) {
            $t->id();
            $t->string('vorname');
            $t->string('nachname');
            $t->softDeletes();
        });
        Schema::create('course_days', function (Blueprint $t) {
            $t->id();
            $t->integer('course_id');
            $t->date('date');
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('course_ratings', function (Blueprint $t) {
            $t->id();
            $t->integer('course_id');
            $t->boolean('skip_course_rating')->default(false);
            $t->integer('do_1')->nullable();
            $t->timestamps();
        });
        (require database_path('migrations/2026_11_06_120000_add_lecturer_plan_to_courses.php'))->up();
        DB::table('persons')->insert([['id' => 101, 'vorname' => 'Abschluss', 'nachname' => 'Dozent'], ['id' => 102, 'vorname' => 'Späterer', 'nachname' => 'Kalenderdozent']]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_ratings_pdf_keeps_closing_person_and_marks_conflicts_or_unbound_ratings(): void
    {
        $plan = ['status' => 'ready', 'closing_assignment_id' => 'A', 'completion_at' => '2026-11-05T16:00:00+01:00', 'assignments' => [
            ['assignment_id' => 'A', 'person_id' => 101, 'contract_id' => '101', 'mitarbeiter_id' => 'MA-A', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-05'],
            ['assignment_id' => 'B', 'person_id' => 102, 'contract_id' => '202', 'mitarbeiter_id' => 'MA-B', 'starts_on' => '2026-11-06', 'ends_on' => '2026-11-06'],
        ]];
        $course = Course::withoutEvents(fn () => Course::create(['title' => 'DLO1', 'klassen_id' => 'synthetic-pdf', 'termin_id' => 'T-1',
            'planned_start_date' => '2026-11-01', 'planned_end_date' => '2026-11-06', 'primary_tutor_person_id' => 101,
            'lecturer_plan' => $plan, 'lecturer_closing_assignment_id' => 'A']));
        $this->assertSame(102, $course->tutor->id);
        DB::table('course_ratings')->insert(['course_id' => $course->id, 'do_1' => 2, 'lecturer_assignment_id' => 'A', 'lecturer_person_id' => 101,
            'lecturer_contract_id' => '101', 'lecturer_staff_id' => 'MA-A']);
        $expected = 'Abschluss Dozent';
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('save')->andReturnUsing(function ($path) use ($pdf) { file_put_contents($path, '%PDF-1.4 fixture'); return $pdf; });
        Pdf::shouldReceive('loadView')->times(3)->andReturnUsing(function ($view, $data) use ($pdf, &$expected) {
            $this->assertSame('pdf.courses.course-ratings', $view);
            $this->assertSame($expected, $data['meta']['tutor_name']);

            return $pdf;
        });
        foreach (['ready', 'conflict', 'unbound-rating'] as $state) {
            if ($state === 'conflict') {
                $plan['status'] = 'conflict';
                $course->forceFill(['lecturer_plan' => $plan])->saveQuietly();
                $expected = 'Zuordnung ungeklärt';
            }
            if ($state === 'unbound-rating') {
                $plan['status'] = 'ready';
                $course->forceFill(['lecturer_plan' => $plan])->saveQuietly();
                DB::table('course_ratings')->update(['lecturer_assignment_id' => null]);
            }
            $path = $course->generateCourseRatingsPdfFile();
            $this->assertFileExists($path);
            @unlink($path);
            @unlink(substr($path, 0, -4));
        }
    }
}
