<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Log;

class File extends Model
{
    // feste Disk für alle Datei-Operationen
    private const DISK = 'public';

    protected $fillable = [
        'filepool_id',
        'user_id',
        'name',
        'path',
        'mime_type',
        'size',
        'expires_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function (File $file) {
            $disk = Storage::disk(self::DISK);

            if ($disk->exists($file->path)) {
                $absPath = $disk->path($file->path);
                $file->uploadImageViaMediaController($absPath);
                unlink($absPath); // Datei nach Upload löschen
            } else {
                Log::warning("File::created - Datei nicht gefunden auf Disk '".self::DISK."': {$file->path}");
            }
        });

        static::deleted(function (File $file) {
            // relativen Pfad an den Controller geben; Disk = public
            $file->deleteImageViaMediaController($file->path);
        });
    }

    public function getIconOrThumbnailAttribute(): string
    {
        $mime = $this->mime_type ?? '';
        $path = $this->path ?? '';

        return match (true) {
            // URL aus dem public-Disk
            str_starts_with($mime, 'image/') => Storage::disk(self::DISK)->url($path),
            str_starts_with($mime, 'video/') => asset('site-images/fileicons/file-video.png'),
            str_starts_with($mime, 'audio/') => asset('site-images/fileicons/file-audio.png'),
            str_contains($mime, 'pdf')       => asset('site-images/fileicons/file-pdf.png'),
            str_contains($mime, 'zip')       => asset('site-images/fileicons/file-zip.png'),
            str_contains($mime, 'excel')     => asset('site-images/fileicons/file-excel.png'),
            str_contains($mime, 'word')      => asset('site-images/fileicons/file-word.png'),
            str_contains($mime, 'text')      => asset('site-images/fileicons/file-text.png'),
            default                          => asset('site-images/fileicons/file-default.png'),
        };
    }

    /**
     * Übergibt eine lokale Datei als UploadedFile an den MediaController::store
     * @param string $absolutePath absoluter Serverpfad (KEINE URL)
     */
    protected function uploadImageViaMediaController(string $absolutePath): ?string
    {
        if (!is_file($absolutePath)) {
            Log::warning("uploadImageViaMediaController: Datei nicht gefunden: {$absolutePath}");
            return null;
        }

        try {
            $uploaded = new UploadedFile(
                $absolutePath,
                basename($absolutePath),
                @mime_content_type($absolutePath) ?: null,
                UPLOAD_ERR_OK,
                true // Testmodus
            );

            $request = Request::create('/admin/media/upload', 'POST');
            $request->files->set('file', $uploaded);

            $controller = app(MediaController::class);
            $response = $controller->store($request);

            if (method_exists($response, 'getData')) {
                $data = $response->getData(true);
                Log::info('Upload via MediaController erfolgreich', ['path' => $data['path'] ?? null]);
                
                // speichern des neuen Pfads in der Datenbank
                $this->update(['path' => $data['path'] ?? null]);
                // Rückgabe des neuen Pfads
                return $data['path'] ?? null;
            }

            Log::warning('Upload via MediaController ohne getData()-Antwort.');
            return null;

        } catch (\Throwable $e) {
            Log::warning('Bild konnte nicht über MediaController hochgeladen werden: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Löscht über den MediaController per relativem Pfad (Disk = public)
     */
    protected function deleteImageViaMediaController(?string $relativePath): void
    {
        if (!$relativePath) return;

        try {
            $request = Request::create('/admin/media/delete', 'POST', ['path' => $relativePath]);

            /** @var MediaController $controller */
            $controller = app(MediaController::class);
            $response = $controller->destroy($request);

            if (method_exists($response, 'getData')) {
                $result = $response->getData(true);
                if (!($result['success'] ?? false)) {
                    Log::warning('Löschen nicht erfolgreich: ' . ($result['message'] ?? 'unbekannt'));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Bild konnte nicht über MediaController gelöscht werden: ' . $e->getMessage());
        }
    }

    /** Morphable Beziehung – z. B. zu User, Course, Task, etc. */
    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
