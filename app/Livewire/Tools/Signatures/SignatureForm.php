<?php

namespace App\Livewire\Tools\Signatures;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\MediaController;

class SignatureForm extends Component
{
    use WithFileUploads;

    public bool $open = false;

    public ?string $fileableType = null;
    public ?int $fileableId = null;

    // z.B. sign_reportbook_trainer
    public string $fileType = 'sign_reportbook_trainer';

    public ?string $label = 'Unterschrift';
    public ?string $confirmText = null;
    public ?string $contextName = null;
    public ?string $signForName = 'Vorgang';

    /**
     * Blade greift auf $defaultConfirmText zu -> muss existieren.
     */
    public ?string $defaultConfirmText = null;

    // Canvas: data:image/png;base64,...
    public ?string $signatureDataUrl = null;

    // optional: Upload (statt Canvas)
    public $upload;

    // optional: alter Pfad zum Löschen
    public ?string $existingPath = null;

    public ?string $errorMsg = null;

    #[On('openSignatureForm')]
    public function openSignatureForm(array $payload): void
    {
        $this->fileableType = $payload['fileableType'] ?? null;
        $this->fileableId   = isset($payload['fileableId']) ? (int) $payload['fileableId'] : null;

        if (isset($payload['fileType'])) {
            $this->fileType = $payload['fileType'];
        }
        $this->label        = $payload['label'] ?? 'Unterschrift';
        $this->signForName  = $payload['signForName'] ?? 'Vorgang';
        $this->contextName  = $payload['contextName'] ?? null;
        $this->confirmText  = $payload['confirmText'] ?? null;

        // optional: alter Pfad zum Löschen (wenn replace)
        $this->existingPath = $payload['existingPath'] ?? null;

        $this->reset(['signatureDataUrl', 'upload', 'errorMsg']);
        $this->open = true;

        // für Blade Variable
        $this->defaultConfirmText = $this->getDefaultConfirmTextProperty();
    }

    public function cancel(): void
    {
        $this->reset(['signatureDataUrl', 'upload', 'errorMsg']);
        $this->open = false;

        $this->dispatch('signatureAborted', [
            'fileableType' => $this->fileableType,
            'fileableId'   => $this->fileableId,
            'fileType'     => $this->fileType,
        ]);
    }

    public function getDefaultConfirmTextProperty(): string
    {
        if ($this->confirmText) {
            return $this->confirmText;
        }

        $subject = $this->signForName ?: ($this->fileableType ? class_basename($this->fileableType) : 'diesem Eintrag');

        if ($this->contextName) {
            return "Ich bestätige, dass meine Angaben zu der <br><strong>{$subject} <br>({$this->contextName})</strong><br> vollständig und korrekt sind.";
        }

        return "Ich bestätige, dass meine Angaben zu {$subject} vollständig und korrekt sind.";
    }

    public function save(): void
    {
        $user = Auth::user();
        if (! $user) {
            $this->errorMsg = 'Nicht eingeloggt.';
            return;
        }

        if (! $this->fileableType || ! $this->fileableId) {
            $this->errorMsg = 'Signatur-Kontext fehlt.';
            return;
        }

        // Fileable laden (wie Standard)
        $fileable = $this->fileableType::find($this->fileableId);
        if (! $fileable) {
            $this->errorMsg = 'Datensatz nicht gefunden.';
            return;
        }
        // disk bleibt wie vorher für Signaturen
        $disk = 'private';


        // Upload bestimmen
        $incomingFile = null;

        if ($this->upload) {
            $this->validate(['upload' => 'image|max:4096']);
            $incomingFile = $this->upload;
        } elseif ($this->signatureDataUrl && str_starts_with($this->signatureDataUrl, 'data:image')) {
            $incomingFile = $this->dataUrlToUploadedFile($this->signatureDataUrl, (int) $user->id);
            if (! $incomingFile) {
                $this->errorMsg = 'Ungültige oder leere Unterschrift.';
                return;
            }
        } else {
            $this->errorMsg = 'Bitte unterschreiben oder ein Bild hochladen.';
            return;
        }

        // Metadaten für DB-Record
        $mimeType = $incomingFile->getMimeType() ?: 'image/png';
        $size     = (int) ($incomingFile->getSize() ?: 0);

        try {
            // Optional: vorhandene Datei löschen (wenn du existingPath übergibst)
            if ($this->existingPath) {
                $this->deleteImageViaMediaController($this->existingPath);
            }

            $path = $this->uploadImageViaMediaController(
                $incomingFile,
                folder: $this->signatureFolder(),
                visibility: 'private'
            );

            if (! $path) {
                throw new \RuntimeException('Upload lieferte keinen Pfad zurück.');
            }

            $file = $fileable->files()->create([
                'user_id'   => $user->id,
                'name'      => $this->label,
                'path'      => $path,
                'disk'      => $disk,
                'type'      => (string) $this->fileType,
                'mime_type' => $mimeType,
                'size'      => $size,
                'checksum'  => hash('sha256', $path . '|' . $size),
            ]);

            // UI schließen + Event wie vorher
            $this->reset(['signatureDataUrl', 'upload', 'errorMsg', 'existingPath']);
            $this->open = false;

            $this->dispatch('signatureCompleted', [
                'fileableType' => $this->fileableType,
                'fileableId'   => $this->fileableId,
                'fileType'     => $this->fileType,
                'fileId'       => $file->id ?? null,
                'path'         => $path,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Signature save failed: ' . $e->getMessage());
            $this->errorMsg = 'Upload fehlgeschlagen.';
        }
    }

    protected function signatureFolder(): string
    {
        $slug = Str::kebab(class_basename($this->fileableType));
        return "signatures/{$slug}/{$this->fileableId}";
    }

    /**
     * data:image/png;base64,... -> temp UploadedFile
     */
    protected function dataUrlToUploadedFile(string $dataUrl, int $userId): ?UploadedFile
    {
        $parts = explode(',', $dataUrl, 2);
        $bin   = base64_decode($parts[1] ?? '', true);

        if ($bin === false || strlen($bin) < 200) {
            return null;
        }

        $tmpDir = storage_path('app/tmp-signatures');
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $tmpFile = $tmpDir . '/sig_draw_' . $userId . '_' . time() . '_' . Str::random(6) . '.png';
        file_put_contents($tmpFile, $bin);

        return new UploadedFile(
            $tmpFile,
            basename($tmpFile),
            'image/png',
            null,
            true
        );
    }

    protected function uploadImageViaMediaController($file, string $folder, string $visibility = 'private'): string
    {
        $request = Request::create(
            '/admin/media/upload',
            'POST',
            [
                'folder'     => $folder,
                'visibility' => $visibility,
            ],
            [],
            ['file' => $file]
        );

        $controller = app(MediaController::class);
        $response   = app()->call([$controller, 'store'], ['request' => $request]);

        if (method_exists($response, 'getData')) {
            $data = $response->getData(true);

            $path = $data['path'] ?? ($data['url'] ?? null);
            if ($path) return $path;
        }

        throw new \RuntimeException('Upload fehlgeschlagen.');
    }

    protected function deleteImageViaMediaController(string $path): void
    {
        if (! $path) return;

        try {
            $request = Request::create('/admin/media/delete', 'DELETE', ['path' => $path]);

            $controller = app(MediaController::class);
            $response   = app()->call([$controller, 'destroy'], ['request' => $request]);

            if (method_exists($response, 'getData')) {
                $data = $response->getData(true);

                if (is_array($data) && array_key_exists('success', $data) && $data['success'] === false) {
                    Log::warning('Signature delete not successful', $data);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Signature could not be deleted: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.tools.signatures.signature-form');
    }
}
