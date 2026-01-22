<?php

namespace App\Livewire\Tools\FilePools;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\File;

class FilePreviewModal extends Component
{
    public bool $open = false;

    // -------------------------
    // Model-Mode (optional, für bestehende Nutzung)
    // -------------------------
    public ?int $fileId = null;
    public ?File $file = null;

    // -------------------------
    // Temp-Path mode
    // -------------------------
    public ?string $disk = null;     // 'local' etc.
    public ?string $path = null;     // relativer Pfad: tmp/cospy/export_123.pdf
    public ?string $name = null;
    public bool $deleteOnClose = false;

    // Preview state (Temp Mode)
    public ?string $mime = null;
    public ?string $dataUrl = null;  // data:<mime>;base64,...

    // Limits
    public int $maxPreviewBytes = 12_000_000; // 12 MB

    // Backward-Compat: bestehendes Browser-Event mit ID
    #[On('filepool:preview')]
    public function handlePreview(int $id): void
    {
        $this->openWith($id);
    }

    public function openWith(int $id): void
    {
        $this->resetTempState();

        $this->fileId = $id;
        $this->file = File::find($id);

        if (! $this->file) {
            $this->dispatch('toast', type: 'error', message: 'Datei nicht gefunden.');
            return;
        }

        $this->open = true;
    }

    // Neu: Pfad-Dispatch
    #[On('filepreview:open')]
    public function openWithPath(string $disk, string $path, ?string $name = null, bool $deleteOnClose = true): void
    {
        $this->resetModelState();

        $disk = $disk ?: 'local';
        $path = ltrim($path, '/');

        // Sicherheits-Gate: nur erlaubte Prefixe
        $allowedPrefixes = [
            'tmp/exports/',
            'tmp/cospy/',
            'exports/tmp/',
        ];

        if (!collect($allowedPrefixes)->contains(fn ($p) => Str::startsWith($path, $p))) {
            $this->dispatch('toast', type: 'error', message: 'Pfad ist nicht erlaubt.');
            return;
        }

        if (!Storage::disk($disk)->exists($path)) {
            $this->dispatch('toast', type: 'error', message: 'Datei nicht gefunden.');
            return;
        }

        $size = Storage::disk($disk)->size($path) ?? 0;
        if ($size > $this->maxPreviewBytes) {
            $this->dispatch('toast', type: 'warning', message: 'Datei zu groß für direkte Vorschau ohne Streaming-Endpunkt.');
            return;
        }

        $mime = Storage::disk($disk)->mimeType($path) ?? 'application/octet-stream';

        // Nur Mimes sinnvoll als data-URL
        $allowedMimes = [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/webp',
            'image/gif',
        ];

        if (!in_array($mime, $allowedMimes, true)) {
            $this->dispatch('toast', type: 'error', message: 'Dateityp wird für Vorschau ohne Streaming nicht unterstützt.');
            return;
        }

        $raw = Storage::disk($disk)->get($path);

        $this->disk = $disk;
        $this->path = $path;
        $this->name = $name ?: basename($path);
        $this->deleteOnClose = $deleteOnClose;

        $this->mime = $mime;
        $this->dataUrl = 'data:' . $mime . ';base64,' . base64_encode($raw);

        $this->open = true;
    }

    /**
     * Einheitlicher Download-Button im Blade:
     * - Model-Mode: nutzt $file->download() (dein bestehender Flow)
     * - Temp-Mode: streamDownload aus Storage (kein Base64-Download)
     */
    public function download(): StreamedResponse
    {
        // Model-Mode
        if ($this->file) {
            // falls dein File-Modell eine download()-Methode hat:
            return $this->file->download();
        }

        // Temp-Mode
        if (!$this->disk || !$this->path) {
            abort(404);
        }

        if (!Storage::disk($this->disk)->exists($this->path)) {
            abort(404);
        }

        $downloadName = $this->name ?: basename($this->path);
        $mime = Storage::disk($this->disk)->mimeType($this->path) ?? 'application/octet-stream';

        return response()->streamDownload(function () use ($mime) {
            $stream = Storage::disk($this->disk)->readStream($this->path);
            if ($stream === false) {
                return;
            }
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $downloadName, [
            'Content-Type' => $mime,
        ]);
    }

    public function close(): void
    {
        $this->open = false;

        // Temp löschen (wenn gewünscht)
        if ($this->deleteOnClose && $this->disk && $this->path) {
            Storage::disk($this->disk)->delete($this->path);
        }

        $this->resetTempState();
        $this->resetModelState();
    }

    public function getUrlProperty(): ?string
    {
        if ($this->file) {
            return $this->file->getEphemeralPublicUrl();
        }
        return null; // Temp-Mode nutzt dataUrl im Blade
    }

    public function getTempSizeFormattedProperty(): string
    {
        if (!$this->disk || !$this->path || !Storage::disk($this->disk)->exists($this->path)) {
            return '';
        }

        $bytes = Storage::disk($this->disk)->size($this->path) ?? 0;

        // simple human readable
        $units = ['B','KB','MB','GB','TB'];
        $i = 0;
        $v = (float) $bytes;
        while ($v >= 1024 && $i < count($units) - 1) {
            $v /= 1024;
            $i++;
        }
        return ($i === 0 ? (string) (int) $v : number_format($v, 1, ',', '.')) . ' ' . $units[$i];
    }

    protected function resetTempState(): void
    {
        $this->disk = null;
        $this->path = null;
        $this->name = null;
        $this->deleteOnClose = false;

        $this->mime = null;
        $this->dataUrl = null;
    }

    protected function resetModelState(): void
    {
        $this->fileId = null;
        $this->file = null;
    }

    public function render()
    {
        return view('livewire.tools.file-pools.file-preview-modal');
    }
}
