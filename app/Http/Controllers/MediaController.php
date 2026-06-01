<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class MediaController extends Controller
{
    protected array $apiSettings = [];

    public function __construct()
    {
        $this->apiSettings['base_api_url'] = Setting::where('key', 'base_api_url')->value('value');
        $this->apiSettings['base_api_key'] = Setting::where('key', 'base_api_key')->value('value');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:512000'], // max 500 MB
            'folder' => 'nullable|string',
            'visibility' => 'nullable|in:public,private',
        ]);

        $file = $request->file('file');
        $stream = fopen($file->getRealPath(), 'r');

        if ($stream === false) {
            return response()->json(['success' => false, 'message' => 'Upload-Datei konnte nicht gelesen werden.'], 500);
        }

        $targetUrl = $this->baseApiUploadUrl();

        try {
            $response = Http::timeout(18000)
                ->connectTimeout(300)
                ->attach(
                    'file',
                    $stream,
                    $file->getClientOriginalName()
                )->withHeaders([
                    'X-API-KEY' => $this->apiSettings['base_api_key'],
                ])->withoutVerifying()->post(
                    $targetUrl,
                    [
                        'folder'     => $request->input('folder'),
                        'visibility' => $request->input('visibility'),
                    ]
                );
        } catch (\Throwable $e) {
            $payload = $this->uploadFailurePayload(
                status: 502,
                file: $file,
                targetUrl: $targetUrl,
                exception: $e,
            );

            Log::warning('Upload via MediaController Exception', $payload['diagnostics']);

            return response()->json($payload, 502);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }


        if ($response->successful()) {
            return response()->json($response->json());
        }

        $payload = $this->uploadFailurePayload(
            status: $response->status(),
            file: $file,
            targetUrl: $targetUrl,
            response: $response,
        );

        Log::warning('Upload via MediaController fehlgeschlagen', $payload['diagnostics']);

        return response()->json($payload, $response->status());
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $response = Http::withHeaders([
            'X-API-KEY' => $this->apiSettings['base_api_key'],
        ])->withoutVerifying()->delete($this->apiSettings['base_api_url'] . '/api/admin/delete', [
            'path' => $request->path,
        ]);

        if ($response->successful()) {
            return response()->json($response->json());
        }

        return response()->json(['success' => false, 'message' => 'Löschen fehlgeschlagen.'], $response->status());
    }

    public function resolve(Request $request): JsonResponse
    {
        $request->validate([
            'file_id' => 'required_without:url|nullable|integer|min:1',
            'url'     => 'required_without:file_id|nullable|string|max:2048',
            'expires' => 'nullable|integer|min:30|max:86400',
            'disk'    => 'nullable|in:private,public',
        ]);

        $payload = [
            // nur setzen, wenn vorhanden
            'expires' => $request->input('expires'),
            'disk'    => $request->input('disk'),
        ];

        if ($request->filled('file_id')) {
            $payload['file_id'] = (int)$request->input('file_id');
        } elseif ($request->filled('url')) {
            $payload['url'] = $request->input('url');
        }

        // Aufruf der Basis-API
        $response = Http::withHeaders([
                'X-API-KEY' => $this->apiSettings['base_api_key'],
            ])
            ->withoutVerifying()
            ->post($this->apiSettings['base_api_url'] . '/api/admin/resolve-file-url', $payload);

        if ($response->successful()) {
            return response()->json($response->json());
        }

        return response()->json([
            'success' => false,
            'message' => 'Auflösung fehlgeschlagen.',
            'status'  => $response->status(),
        ], $response->status());
    }

    protected function baseApiUploadUrl(): string
    {
        return rtrim((string) ($this->apiSettings['base_api_url'] ?? ''), '/') . '/api/admin/upload';
    }

    protected function uploadFailurePayload(
        int $status,
        $file,
        string $targetUrl,
        mixed $response = null,
        ?\Throwable $exception = null,
    ): array {
        $responseJson = null;
        $responseBody = null;

        if ($response) {
            try {
                $responseJson = $response->json();
            } catch (\Throwable) {
                $responseJson = null;
            }

            try {
                $responseBody = substr((string) $response->body(), 0, 1000);
            } catch (\Throwable) {
                $responseBody = null;
            }
        }

        $diagnostics = [
            'status' => $status,
            'reason' => $this->uploadFailureReason($status),
            'hint' => $this->uploadFailureHint($status),
            'target_url' => $targetUrl,
            'file_name' => $file?->getClientOriginalName(),
            'file_size_bytes' => $file?->getSize(),
            'file_size_mb' => $file?->getSize() ? round($file->getSize() / 1048576, 2) : null,
            'admin_php_limits' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'memory_limit' => ini_get('memory_limit'),
                'max_input_time' => ini_get('max_input_time'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'response_json' => $responseJson,
            'response_body_preview' => $responseBody,
            'exception' => $exception ? [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ] : null,
        ];

        return [
            'success' => false,
            'message' => $diagnostics['reason'],
            'status' => $status,
            'diagnostics' => $diagnostics,
        ];
    }

    protected function uploadFailureReason(int $status): string
    {
        return match ($status) {
            413 => 'Upload zur Base wurde abgelehnt, weil der Request zu gross ist.',
            422 => 'Upload zur Base wurde durch die Base-Validierung abgelehnt.',
            401, 403 => 'Upload zur Base wurde wegen API-Key/Berechtigung abgelehnt.',
            404 => 'Upload-Endpunkt der Base wurde nicht gefunden.',
            408, 504 => 'Upload zur Base ist in ein Timeout gelaufen.',
            502, 503 => 'Upload zur Base konnte wegen Gateway/Verbindungsproblem nicht abgeschlossen werden.',
            default => 'Upload zur Base ist fehlgeschlagen.',
        };
    }

    protected function uploadFailureHint(int $status): string
    {
        return match ($status) {
            413 => 'Pruefe auf der Base-Domain post_max_size, upload_max_filesize, nginx client_max_body_size, Apache LimitRequestBody und ModSecurity SecRequestBodyLimit.',
            422 => 'Pruefe diagnostics.response_json, besonders file.max oder file.uploaded.',
            401, 403 => 'Pruefe base_api_key in Admin und Base.',
            404 => 'Pruefe base_api_url und die Route /api/admin/upload.',
            408, 504 => 'Pruefe PHP/Webserver-Timeouts und Proxy-Timeouts auf Admin und Base.',
            502, 503 => 'Pruefe Erreichbarkeit der Base-Domain, SSL/Proxy und PHP-FPM/Webserver auf Base.',
            default => 'Pruefe diagnostics.response_json und diagnostics.response_body_preview im Log.',
        };
    }
}
