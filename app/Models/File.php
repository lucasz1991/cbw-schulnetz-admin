<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\MediaController;

class File extends Model
{
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

        static::created(function ($file) {
            $absPath = Storage::path($file->path);
            if (is_file($absPath)) {
                $file->uploadImageViaMediaController($absPath);
            }
        });

        static::deleted(function ($file) {
            $file->deleteImageViaMediaController(Storage::url($file->path));
        });
    }

    public function getIconOrThumbnailAttribute(): string
    {
        $mime = $this->mime_type ?? '';
        $path = $this->path ?? '';
        return match (true) {
            str_starts_with($mime, 'image/') => Storage::url($path),
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

    protected function uploadImageViaMediaController($file)
    {
                // Temporäres Request-Objekt mit dem File als 'file'
        $request = Request::create('/admin/media/upload', 'POST', [], [], ['file' => $file]);

        // MediaController manuell instanziieren und aufrufen
        $controller = new MediaController();
        $response = $controller->store($request);

        if (method_exists($response, 'getData')) {
            return $response->getData(true)['path'] ?? '';
        }

        throw new \Exception('Upload fehlgeschlagen.');
    }

    protected function deleteImageViaMediaController($path)
    {
        if (!$path) {
            return;
        }

        try {
            // Temporären Request mit POST-Daten (obwohl eigentlich DELETE, das ist okay für internen Aufruf)
            $request = Request::create('/admin/media/delete', 'POST', ['path' => $path]);

            // MediaController aufrufen
            $controller = new MediaController();
            $response = $controller->destroy($request);

            if (method_exists($response, 'getData')) {
                $result = $response->getData(true);
                if (!($result['success'] ?? false)) {
                    \Log::warning('Löschen nicht erfolgreich: ' . ($result['message'] ?? 'unbekannt'));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Bild konnte nicht über MediaController gelöscht werden: ' . $e->getMessage());
        }
    }

    /**
     * Morphable Beziehung – z. B. zu User, Course, Task, etc.
     */
    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
