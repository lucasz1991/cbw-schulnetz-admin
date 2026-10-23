<?php

namespace App\Services\ReportBooks;

use App\Models\File;
use App\Models\ReportBook;
use Illuminate\Support\Facades\DB;

class SignatureEvidence
{
    public static function currentFile(ReportBook $book, string $role): ?File
    {
        return $book->files()->where('type', 'sign_reportbook_'.$role)
            ->whereNotIn('id', (array) data_get($book->settings, 'reportbook_invalidated_signature_ids', []))
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())
            ->latest('id')->first();
    }

    public static function fileIsPreserved(File $file): bool
    {
        if (! LocationPolicy::schemaReady()) return false;
        if ($file->fileable_type === ReportBook::class && str_starts_with((string) $file->type, 'sign_reportbook_')) {
            return true;
        }
        return self::pathIsPreserved((string) $file->path);
    }

    public static function pathIsPreserved(string $path): bool
    {
        if ($path === '' || ! LocationPolicy::schemaReady()) return false;
        if (str_starts_with($path, 'signatures/report-book/')) return true;
        $files = File::query()->where('path', $path)->where('fileable_type', ReportBook::class)
            ->whereIn('type', ['sign_reportbook_participant', 'sign_reportbook_trainer'])
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->get();
        if ($files->isNotEmpty()) return true;
        foreach (DB::table('report_book_policy_audit')->whereIn('event', ['manual_signatures_preserved', 'entry_approved'])
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->cursor() as $audit) {
            $payload = json_decode($audit->payload, true);
            foreach ((array) data_get($payload, $audit->event === 'entry_approved' ? 'context.signature_files' : 'signature_files', []) as $snapshot) {
                if (($snapshot['path'] ?? null) === $path) return true;
            }
        }
        return false;
    }

}
