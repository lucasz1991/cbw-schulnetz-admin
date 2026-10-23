<?php

namespace App\Services\ReportBooks;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class StampStorage
{
    public static function verify(string $path, string $hash, string $mime): bool
    {
        try {
            return (bool) (self::request('verify', compact('path', 'hash', 'mime'))['verified'] ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function store(UploadedFile $file): array
    {
        validator(['stampUpload' => $file], ['stampUpload' => 'required|file|max:4096|mimetypes:image/png,image/jpeg'])->validate();
        $payload = self::request('', [], $file);
        if (! ($payload['success'] ?? false) || empty($payload['stamp_path'])) {
            throw ValidationException::withMessages(['stampUpload' => 'Der private Stempelupload ist fehlgeschlagen.']);
        }

        return $payload;
    }

    public static function preview(string $path, string $hash, string $mime): ?string
    {
        try {
            return self::request('verify', compact('path', 'hash', 'mime') + ['preview' => true])['preview'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function request(string $suffix, array $data, ?UploadedFile $file = null): array
    {
        $url = rtrim((string) Setting::getValueUncached('api', 'base_api_url'), '/');
        $key = Setting::getValueUncached('api', 'base_api_key');
        if ($url === '' || ! is_string($key) || $key === '') {
            throw ValidationException::withMessages(['stampUpload' => 'Die Verbindung zur Schulnetz-Base ist nicht eingerichtet.']);
        }
        $request = Http::acceptJson()->withHeaders(['X-API-KEY' => $key])->connectTimeout(10)->timeout(30);
        if ($file) {
            $request = $request->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName());
        }
        try {
            $response = $request->post($url.'/api/admin/reportbook-stamps'.($suffix !== '' ? '/'.$suffix : ''), $data);
            if (! $response->successful()) {
                throw new \RuntimeException('Stamp bridge request failed.');
            }
            $payload = $response->json();
            if (! is_array($payload)) {
                throw new \RuntimeException('Invalid stamp bridge response.');
            }

            return $payload;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['stampUpload' => 'Die Stempeldatei konnte nicht in der Schulnetz-Base geprüft werden. Bitte erneut versuchen.']);
        }
    }
}
