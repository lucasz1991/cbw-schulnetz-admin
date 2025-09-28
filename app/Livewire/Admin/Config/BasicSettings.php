<?php

namespace App\Livewire\Admin\Config;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Http\UploadedFile;

class BasicSettings extends Component
{
    use WithFileUploads;

    public $baseUrl;

    public $companyName, $appName, $timezone, $contactEmail, $maintenanceMode;

    public $favicon, $logoSquare, $logoHorizontal, $logoVertical;
    public $faviconPreview, $logoSquarePreview, $logoHorizontalPreview, $logoVerticalPreview;
    
    public $primaryColor, $secondaryColor, $accentColor, $backgroundColor, $textColor;


    public function mount()
    {
        $this->baseUrl = Setting::where('key', 'base_api_url')->value('value');

        $this->maintenanceMode = Setting::getValue('base', 'maintenance_mode');

        $this->companyName = Setting::getValue('base', 'company_name');
        $this->appName = Setting::getValue('base', 'app_name');
        $this->timezone = Setting::getValue('base', 'timezone');
        $this->contactEmail = Setting::getValue('base', 'contact_email');

        // Farben laden
        $this->primaryColor = Setting::getValue('base', 'primary_color');
        $this->secondaryColor = Setting::getValue('base', 'secondary_color');
        $this->accentColor = Setting::getValue('base', 'accent_color');
        $this->backgroundColor = Setting::getValue('base', 'background_color');
        $this->textColor = Setting::getValue('base', 'text_color');

        // Bildvorschauen setzen
        $this->faviconPreview = $this->getImageUrlAttribute(Setting::getValue('base', 'favicon'));
        $this->logoSquarePreview = $this->getImageUrlAttribute(Setting::getValue('base', 'logo_square'));
        $this->logoHorizontalPreview = $this->getImageUrlAttribute(Setting::getValue('base', 'logo_horizontal'));
        $this->logoVerticalPreview = $this->getImageUrlAttribute(Setting::getValue('base', 'logo_vertical'));

    }

    public function getImageUrlAttribute($url)
    {
        return $url ? $this->baseUrl . '/storage/' . $url : null;
    }

    public function saveSettings()
    {
        $this->validate([
            'companyName' => 'nullable|string|max:255',
            'appName' => 'nullable|string|max:255',
            'contactEmail' => 'nullable|email',
            'primaryColor' => 'nullable',
            'secondaryColor' => 'nullable',
            'accentColor' => 'nullable',
            'backgroundColor' => 'nullable',
            'textColor' => 'nullable',
            'favicon' => 'nullable|image|max:1024',
            'logoSquare' => 'nullable|image|max:2048',
            'logoHorizontal' => 'nullable|image|max:2048',
            'logoVertical' => 'nullable|image|max:2048',
        ]);

        // Grundlegende Einstellungen speichern
        Setting::setValue('base', 'company_name', $this->companyName);
        Setting::setValue('base', 'app_name', $this->appName);
        Setting::setValue('base', 'timezone', $this->timezone);
        Setting::setValue('base', 'contact_email', $this->contactEmail);
        Setting::setValue('base', 'maintenance_mode', $this->maintenanceMode);

        // Farben speichern
        Setting::setValue('base', 'primary_color', $this->primaryColor);
        Setting::setValue('base', 'secondary_color', $this->secondaryColor);
        Setting::setValue('base', 'accent_color', $this->accentColor);
        Setting::setValue('base', 'background_color', $this->backgroundColor);
        Setting::setValue('base', 'text_color', $this->textColor);

        // Bilder speichern und URLs setzen
        // Bilder via MediaController hochladen (public) und Settings + Previews setzen
        $map = [
            'favicon'        => ['setting' => 'favicon',         'previewProp' => 'faviconPreview'],
            'logoSquare'     => ['setting' => 'logo_square',     'previewProp' => 'logoSquarePreview'],
            'logoHorizontal' => ['setting' => 'logo_horizontal', 'previewProp' => 'logoHorizontalPreview'],
            'logoVertical'   => ['setting' => 'logo_vertical',   'previewProp' => 'logoVerticalPreview'],
        ];

        foreach ($map as $prop => $cfg) {
            if ($this->$prop) {
                // altes Bild löschen (falls im Setting ein Pfad steht)
                $oldPath = (string) Setting::getValue('base', $cfg['setting']);
                if ($oldPath && !str_starts_with($oldPath, 'http')) {
                    // nur versuchen zu löschen, wenn es nach einem Pfad aussieht
                    $this->deleteImageViaMediaController($oldPath);
                }

                // neu hochladen (public) – gibt PATH zurück
                $path = $this->uploadImageViaMediaController(
                    $this->$prop,
                    folder: 'settings/branding',
                    visibility: 'public'
                );

                // Setting mit dem Pfad speichern (robust fürs spätere Löschen)
                Setting::setValue('base', $cfg['setting'], $path);

                // Preview-URL für die UI aktualisieren (gleiche App -> Storage::url funktioniert)
                // Falls dein Media-Service extern wäre, würdest du die URL direkt aus der Upload-Antwort nehmen.
                $previewUrl = $this->baseUrl . $path;
                $this->{$cfg['previewProp']} = $previewUrl;
            }
        }
        
        $this->mount();
        
        session()->flash('success', 'Einstellungen erfolgreich gespeichert.');
    }

    protected function deleteImageViaMediaController(string $path): void
    {
        if (!$path) return;

        try {
            // DELETE-Request mit Body (Laravel erlaubt das)
            $request = Request::create(
                '/admin/media/delete',
                'DELETE',
                ['path' => $path]
            );

            /** @var \App\Http\Controllers\MediaController $controller */
            $controller = app(MediaController::class);
            $response = app()->call([$controller, 'destroy'], ['request' => $request]);

            $payload = method_exists($response, 'getData') ? $response->getData(true) : null;

            // Falls deine MediaController-Antwort 'success' nutzt:
            if (is_array($payload) && isset($payload['success']) && $payload['success'] === false) {
                \Log::warning('Löschen nicht erfolgreich', $payload);
            }
        } catch (\Throwable $e) {
            \Log::warning('Bild konnte nicht über MediaController gelöscht werden: '.$e->getMessage());
        }
    }

    protected function uploadImageViaMediaController($file, string $folder = 'uploads/webpages', string $visibility = 'public'): string
    {
        // Request inkl. File & Inputs erstellen
        $request = Request::create(
            '/admin/media/upload',
            'POST',
            [
                'folder'     => $folder,
                'visibility' => $visibility,
            ],
            [], // cookies
            ['file' => $file], // FILES: UploadedFile|TemporaryUploadedFile
        );

        // Controller via Container aufrufen (DI-freundlich)
        /** @var \App\Http\Controllers\MediaController $controller */
        $controller = app(MediaController::class);
        $response = app()->call([$controller, 'store'], ['request' => $request]);

        // JsonResponse -> Array
        $payload = method_exists($response, 'getData') ? $response->getData(true) : null;
        if (!is_array($payload) || !($payload['success'] ?? true)) {
            throw new \RuntimeException('Upload fehlgeschlagen.');
        }

        // Für public bekommst du i.d.R. sowohl `url` als auch `path`.
        // In der DB speichere ich gern `path` (robuster fürs Löschen).
        return $payload['path'] ?? ($payload['url'] ?? '');
    }


    public function render()
    {
        return view('livewire.admin.config.basic-settings');
    }
}
