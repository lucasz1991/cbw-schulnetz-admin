<?php

namespace App\Livewire\Admin\Courses;

use Livewire\Component;
use App\Models\Course;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Gate;

class CourseExportModal extends Component
{
    /** Modal sichtbar? */
    public bool $showModal = false;

    /** IDs der zu exportierenden Kurse */
    public array $courseIds = [];

    /**
     * Export-Optionen (passen zu den Toggle-Buttons im Blade)
     */
    public bool $includeDocumentation = false; // Dozenten-Dokumentationen
    public bool $includeRedThread     = false; // Roter Faden
    public bool $includeParticipants  = false; // Teilnehmer Bildungsmittel Bestätigungen
    public bool $includeAttendance    = false; // Anwesenheitslisten
    public bool $includeExamResults   = false; // Prüfungs-Ergebnisse
    public bool $includeTutorData     = false; // Dozenten-Rechnung

    /** ZIP-Option */
    public bool $asZip = true;

    /** Client-seitiges Loading-Flag fuer Export */
    public bool $isExporting = false;

    /** Optional: Name des Exports */
    public ?string $exportName = null;

    protected $listeners = [
        'openCourseExportModal' => 'open',
    ];

    public function open(array $courseIds = []): void
    {
        Gate::authorize('courses.export');

        $this->resetValidation();

        $this->courseIds = $courseIds;

        // Exportname: bei einem Baustein sprechender Name, sonst Sammelname
        $isSingleCourse = count($courseIds) === 1;
        if ($isSingleCourse) {
            $course = Course::find($courseIds[0]);
            $this->exportName = $course
                ? $course->getExportBaseName()
                : 'Baustein_Export_' . now()->format('Y-m-d');
        } else {
            $this->exportName = count($courseIds) . '_Bausteine_Export_' . now()->format('Y-m-d');
        }

        // Defaults für die Toggle-Buttons
        $this->includeDocumentation = true;
        $this->includeRedThread     = true;
        $this->includeParticipants  = true;
        $this->includeAttendance    = true;
        $this->includeExamResults   = true;
        $this->includeTutorData     = Gate::allows('invoices.view'); // Dozenten-Rechnung nur standardmäßig, wenn Berechtigung besteht

        $this->asZip = true;

        $this->showModal = true;
        $this->isExporting = false;
    }

    public function close(): void
    {
        $this->showModal = false;
    }

    public function export()
    {
        if (empty($this->courseIds)) {
            $this->addError('courseIds', 'Es wurden keine Bausteine ausgewählt.');
            return;
        }

        $this->isExporting = true;

        $courses = $this->selectedCourses;

        $settings = [
            'includeDocumentation' => $this->includeDocumentation,
            'includeRedThread'     => $this->includeRedThread,
            'includeParticipants'  => $this->includeParticipants,
            'includeAttendance'    => $this->includeAttendance,
            'includeExamResults'   => $this->includeExamResults,
            'includeTutorData'     => $this->includeTutorData,
            'asZip'                => $this->asZip,
            'exportName'           => $this->exportName,
        ];

        if ($courses->count() === 1) {
            /** @var \App\Models\Course $course */
            $course   = $courses->first();
            $response = $course->exportAll($settings);
        } else {
            $response = $this->exportMultipleAll($courses, $settings);
        }

        try {
            $this->showModal = false;
            $this->dispatch('toast', type: 'success', message: 'Download wurde gestartet.');

            return $response;
        } finally {
            $this->isExporting = false;
        }
    }

    /**
     * Erzeugt eine ZIP-Datei, die pro Kurs eine einzelne Kurs-ZIP enthält.
     * Rückgabe: Pfad zur Master-ZIP im tmp oder null, wenn nichts exportierbar.
     */
    public function generateExportMultipleAllZipFile($courses, array $settings = []): ?string
    {
        // Default-Gesamtname
        $exportBaseName = $settings['exportName']
            ?? ($courses->count() . '_Bausteine_Export_' . now()->format('Y-m-d'));

        $masterZipPath = tempnam(sys_get_temp_dir(), 'courses_zip_');
        $zip           = new ZipArchive();

        if ($zip->open($masterZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Master-ZIP-Archiv konnte nicht erstellt werden.');
        }

        $courseZipFiles = [];
        $addedAny       = false;

        foreach ($courses as $course) {
            if (! $course instanceof Course) {
                continue;
            }

            // Pro Kurs eigener Exportname (innerhalb der Master-ZIP)
            $perCourseSettings              = $settings;
            $perCourseSettings['asZip']     = true; // Sicherheitshalber
            $perCourseSettings['exportName'] = $course->getExportBaseName();

            // nutzt die generateExportAllZipFile()-Methode am Course
            $courseZipPath = $course->generateExportAllZipFile($perCourseSettings);

            if ($courseZipPath && file_exists($courseZipPath)) {
                $entryName = $perCourseSettings['exportName'] . '.zip'; // schöner Dateiname im ZIP
                $zip->addFile($courseZipPath, $entryName);

                $courseZipFiles[] = $courseZipPath;
                $addedAny         = true;
            }
        }

        $zip->close();

        // Einzelne Kurs-ZIPs nach dem Packen löschen
        foreach ($courseZipFiles as $file) {
            @unlink($file);
        }

        if (! $addedAny) {
            @unlink($masterZipPath);
            return null;
        }

        return $masterZipPath;
    }

    /**
     * Streamt die Master-ZIP mit allen Kurs-ZIPs.
     */
    protected function exportMultipleAll($courses, array $settings): ?StreamedResponse
    {
        $zipPath = $this->generateExportMultipleAllZipFile($courses, $settings);

        if (! $zipPath || ! file_exists($zipPath)) {
            abort(404, 'Für die ausgewählten Bausteine gibt es keine exportierbaren Dokumente.');
        }

        $exportBaseName = $settings['exportName']
            ?? ($courses->count() . '_Bausteine_Export_' . now()->format('Y-m-d'));

        $zipFileName = $exportBaseName . '.zip';

        return response()->streamDownload(function () use ($zipPath) {
            readfile($zipPath);
            @unlink($zipPath);
        }, $zipFileName);
    }

    public function getSelectedCoursesProperty()
    {
        return Course::whereIn('id', $this->courseIds)->get();
    }

    public function render()
    {
        return view('livewire.admin.courses.course-export-modal');
    }
}
