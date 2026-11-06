<?php

namespace App\Services\Courses;

use App\Models\Course;
use App\Models\File;
use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/** Export all invoice owners/versions without replacing or deleting their originals. */
class CourseInvoiceExport
{
    public function status(Course $course): array
    {
        $assignments = CourseLecturerAccess::assignments($course);
        $files = $course->files()->where('type', 'invoice')->get();
        $covered = collect($assignments)->filter(fn ($assignment) => $files->contains(fn ($file) => (string) $file->lecturer_assignment_id === (string) $assignment['assignment_id']
            && (int) $file->lecturer_person_id === (int) $assignment['person_id']))->count();

        return ['expected' => count($assignments), 'covered' => $covered, 'files' => $files->count(),
            'unassigned' => $files->filter(fn ($file) => ! $file->lecturer_assignment_id || ! $file->lecturer_person_id)->count()];
    }

    public function files(Course $course): Collection
    {
        Gate::authorize('courses.export');
        Gate::authorize('invoices.view');

        return $course->files()->where('type', 'invoice')->orderBy('id')->get();
    }

    public function personLabel(File $file): string
    {
        $person = $file->lecturer_person_id ? Person::query()->find($file->lecturer_person_id) : null;
        if (! $person && $file->user_id && ! $file->lecturer_person_id) {
            // An existing account proves ownership; never infer the current lecturer.
            $owners = Person::query()->where('user_id', $file->user_id)->limit(2)->get();
            $person = $owners->count() === 1 ? $owners->first() : null;
        }

        return $person ? trim($person->vorname.' '.$person->nachname) : 'Zuordnung offen';
    }

    public function filename(Course $course, File $file): string
    {
        $parts = [
            'Dozentenrechnung', $course->klassen_id ?: $course->id,
            $this->personLabel($file) !== 'Zuordnung offen' ? $this->personLabel($file) : 'Bestandsbeleg',
        ];
        if ($file->lecturer_contract_id) {
            $parts[] = 'Vertrag-'.$file->lecturer_contract_id;
        }
        if ($file->lecturer_assignment_id) {
            $parts[] = 'Einsatz-'.$file->lecturer_assignment_id;
        }
        $parts[] = 'v'.($file->document_revision ?: $file->id);
        $parts[] = 'Datei-'.$file->id;

        return Str::slug(implode('_', $parts), '_').'.pdf';
    }

    public function context(Course $course, File $file): array
    {
        $plan = is_array($course->lecturer_plan) ? $course->lecturer_plan : [];
        $assignment = collect($plan['assignments'] ?? [])->firstWhere('assignment_id', $file->lecturer_assignment_id);
        $contract = collect($plan['contracts'] ?? [])->first(fn ($row) => (string) ($row['contract_id'] ?? $row['uid'] ?? '') === (string) $file->lecturer_contract_id);
        $period = $assignment ? ($assignment['starts_on'].' bis '.$assignment['ends_on']) : null;

        return [
            'person' => $this->personLabel($file),
            'contract' => $contract['kennung'] ?? ($file->lecturer_contract_id ? 'Vertrag '.$file->lecturer_contract_id : null),
            'period' => $period,
        ];
    }

    public function copyFile(Course $course, File $file): ?string
    {
        Gate::authorize('courses.export');
        Gate::authorize('invoices.view');
        abort_unless($course->files()->whereKey($file->id)->where('type', 'invoice')->exists(), 404);

        $input = null;
        $disk = Storage::disk($file->disk ?: 'public');
        if ($file->path && $disk->exists($file->path)) {
            $input = $disk->readStream($file->path);
        } else {
            $url = $file->getEphemeralPublicUrl();
            if ($url) {
                $input = @fopen($url, 'rb');
            }
        }
        if (! is_resource($input)) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'course_invoice_');
        $output = $path ? @fopen($path, 'wb') : false;
        if (! $output) {
            fclose($input);
            if ($path) {
                @unlink($path);
            }

            return null;
        }
        try {
            try {
                $copied = stream_copy_to_stream($input, $output);
            } finally {
                fclose($input);
                fclose($output);
            }
        } catch (\Throwable $error) {
            @unlink($path);
            throw $error;
        }
        if ($copied === false) {
            @unlink($path);

            return null;
        }

        return $path;
    }

    /** @return array<string,string> ZIP entry name => generated temporary copy */
    public function copies(Course $course): array
    {
        $copies = [];
        try {
            foreach ($this->files($course) as $file) {
                $path = $this->copyFile($course, $file);
                if (! $path) {
                    throw new \RuntimeException('Eine Dozentenrechnung ist derzeit nicht lesbar. Bitte erneut versuchen.');
                }
                $copies[$this->filename($course, $file)] = $path;
            }

            return $copies;
        } catch (\Throwable $error) {
            foreach ($copies as $path) {
                @unlink($path);
            }
            throw $error;
        }
    }

    public function zip(Course $course): ?string
    {
        $copies = $this->copies($course);
        if (! $copies) {
            return null;
        }
        $path = tempnam(sys_get_temp_dir(), 'course_invoices_');
        $zip = new ZipArchive;
        $opened = false;
        try {
            if (! $path || $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Die Rechnungsdateien konnten nicht zusammengefasst werden.');
            }
            $opened = true;
            foreach ($copies as $name => $copy) {
                if (! $zip->addFile($copy, $name)) {
                    throw new \RuntimeException('Eine Rechnung konnte nicht in das ZIP aufgenommen werden.');
                }
            }
            if (! $zip->close()) {
                throw new \RuntimeException('Das Rechnungs-ZIP konnte nicht abgeschlossen werden.');
            }
            $opened = false;

            return $path;
        } catch (\Throwable $error) {
            if ($opened) {
                try {
                    @$zip->close();
                } catch (\Throwable) {
                }
            }
            if ($path) {
                @unlink($path);
            }
            throw $error;
        } finally {
            foreach ($copies as $copy) {
                @unlink($copy);
            }
        }
    }
}
