<?php

namespace App\Livewire\Admin\Config;

use App\Http\Controllers\MediaController;
use App\Models\Setting;
use Illuminate\Http\Request;
use Livewire\Component;
use Livewire\WithFileUploads;

class RoterFadenTemplateSettings extends Component
{
    use WithFileUploads;

    private const SETTINGS_TYPE = 'course_media';
    private const SETTINGS_KEY = 'roter_faden_template';

    public $templateUpload = null;
    public ?array $template = null;

    public function mount(): void
    {
        $this->loadTemplate();
    }

    public function save(): void
    {
        $this->validate([
            'templateUpload' => 'required|file|max:30720|mimes:pdf,doc,docx,odt',
        ], [], [
            'templateUpload' => 'Roter-Faden-Vorlage',
        ]);

        $oldTemplate = $this->template;

        $payload = $this->uploadTemplateViaMediaController();

        Setting::setValue(self::SETTINGS_TYPE, self::SETTINGS_KEY, [
            'path' => $payload['path'] ?? null,
            'disk' => 'private',
            'name' => $payload['original'] ?? $this->templateUpload->getClientOriginalName(),
            'mime' => $payload['mime'] ?? $this->templateUpload->getMimeType(),
            'size' => $payload['size'] ?? $this->templateUpload->getSize(),
            'uploaded_at' => now()->toIso8601String(),
        ]);

        $this->deleteStoredTemplate($oldTemplate);
        $this->reset('templateUpload');
        $this->loadTemplate();

        session()->flash('success', 'Roter-Faden-Vorlage gespeichert.');
    }

    public function remove(): void
    {
        $oldTemplate = $this->template;

        Setting::setValue(self::SETTINGS_TYPE, self::SETTINGS_KEY, null);

        $this->deleteStoredTemplate($oldTemplate);
        $this->template = null;
        $this->reset('templateUpload');

        session()->flash('success', 'Roter-Faden-Vorlage entfernt.');
    }

    public function render()
    {
        return view('livewire.admin.config.roter-faden-template-settings');
    }

    protected function loadTemplate(): void
    {
        $template = Setting::getValueUncached(self::SETTINGS_TYPE, self::SETTINGS_KEY);

        $this->template = is_array($template) && filled($template['path'] ?? null)
            ? $template
            : null;
    }

    protected function uploadTemplateViaMediaController(): array
    {
        $request = Request::create(
            '/admin/media/upload',
            'POST',
            [
                'folder' => 'settings/roter-faden-template',
                'visibility' => 'private',
            ],
            [],
            ['file' => $this->templateUpload],
        );

        /** @var \App\Http\Controllers\MediaController $controller */
        $controller = app(MediaController::class);
        $response = app()->call([$controller, 'store'], ['request' => $request]);

        $payload = method_exists($response, 'getData') ? $response->getData(true) : null;

        if (! is_array($payload) || ! ($payload['success'] ?? false) || blank($payload['path'] ?? null)) {
            throw new \RuntimeException('Upload der Roter-Faden-Vorlage fehlgeschlagen.');
        }

        return $payload;
    }

    protected function deleteStoredTemplate(?array $template): void
    {
        $path = $template['path'] ?? null;

        if (blank($path)) {
            return;
        }

        try {
            $request = Request::create(
                '/admin/media/delete',
                'DELETE',
                [
                    'path' => $path,
                    'visibility' => $template['disk'] ?? 'private',
                ]
            );

            /** @var \App\Http\Controllers\MediaController $controller */
            $controller = app(MediaController::class);
            app()->call([$controller, 'destroy'], ['request' => $request]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function getTemplateSizeFormattedProperty(): ?string
    {
        if (! $this->template || ! isset($this->template['size'])) {
            return null;
        }

        $bytes = (int) $this->template['size'];

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        }

        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }
}
