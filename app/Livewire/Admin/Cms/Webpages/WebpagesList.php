<?php

namespace App\Livewire\Admin\Cms\Webpages;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Http\Request;
use App\Http\Controllers\MediaController;
use App\Models\WebPage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Http\UploadedFile;



class WebpagesList extends Component
{
    use WithPagination, WithFileUploads;

    public $title, $slug, $meta_title, $meta_description, $meta_keywords, $canonical_url, $robots_meta;
    public $og_title, $og_description, $og_image;
    public $custom_css, $custom_js, $custom_meta;
    public $icon, $header_image, $new_header_image, $is_active, $published_from, $published_until, $language, $showHeader, $header_image_positioning;
    public $editingId = null;
    public $modalOpen = false;
    public $page = null;
    public $header_image_url = null;





    public function create()
    {
        $this->resetForm();
        $this->modalOpen = true;
    }

    public function edit($id)
    {
        $this->page = WebPage::findOrFail($id);
        $this->editingId = $this->page->id;
        $this->fill($this->page->toArray());
        $this->header_image = $this->page->header_image; 
        $this->header_image_url = $this->page->getHeaderImageUrlAttribute(); 
        $this->showHeader = $this->page->settings['showHeader'] ?? false;
        $this->header_image_positioning = $this->page->settings['header_image_positioning'] ?? 'center';

        $this->modalOpen = true;
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|string|max:255|unique:web_pages,title,' . $this->editingId,
            'slug' => 'required|string|max:255|unique:web_pages,slug,' . $this->editingId,
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'custom_meta' => 'nullable|array',
            'new_header_image' => 'nullable|image|mimes:jpeg,png,webp|max:16048',
            'og_title'          => 'nullable|string|max:255',
            'og_description'    => 'nullable|string|max:500',
            'custom_css'        => 'nullable|string',
            'custom_js'         => 'nullable|string',
            'custom_meta'       => 'nullable',

        ]);

        if (!$this->slug) {
            $this->slug = Str::slug($this->title);
        }

                // JSON aus Textarea in Array parsen + validieren
        $customMetaArr = [];
        if (!empty($this->custom_meta)) {
            try {
                $decoded = json_decode($this->custom_meta, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    throw new \RuntimeException('JSON muss ein Array sein.');
                }
                $customMetaArr = $this->normalizeCustomMetaArray($decoded);
            } catch (\Throwable $e) {
                $this->addError('custom_meta', 'Ungültiges JSON: '.$e->getMessage());
                return; // Speichern abbrechen
            }
        }

        // OG-Fallbacks (optional sinnvoll)
        if (!$this->og_title && $this->meta_title) {
            $this->og_title = $this->meta_title;
        }
        if (!$this->og_description && $this->meta_description) {
            $this->og_description = $this->meta_description;
        }
        if (!$this->robots_meta) {
            $this->robots_meta = 'index,follow';
        }

        // Falls ein neues Bild hochgeladen wurde, speichere es
        if ($this->new_header_image) {
            if ($this->header_image) {
                $this->deleteImageViaMediaController($this->header_image); // Altes Bild löschen
            }
            // Bildgröße überprüfen und korrigieren sodass es höchstens 700kb
            if ($this->new_header_image->getSize() > 700 * 1024) {
                $this->new_header_image = $this->resizeImage($this->new_header_image, 700);
            }
            $this->header_image = $this->uploadImageViaMediaController($this->new_header_image);
        }

        $data = [
            'title' => $this->title,
            'slug' => $this->slug,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'canonical_url' => $this->canonical_url,
            'robots_meta' => $this->robots_meta,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'custom_css' => $this->custom_css,
            'custom_js' => $this->custom_js,
            'custom_meta' => $customMetaArr,
            'icon' => $this->icon,
            'header_image' => $this->header_image,
            'is_active' => $this->is_active,
            'published_from' => $this->published_from,
            'published_until' => $this->published_until,
            'settings' => [ 
                'showHeader' => $this->showHeader, 
                'header_image_positioning' => $this->header_image_positioning,
            ],
        ];

        if ($this->editingId) {
            WebPage::find($this->editingId)->update($data);
        } else {
            WebPage::create($data);
        }

        $this->modalOpen = false;
        $this->resetForm();
    }

    public function delete($id)
    {
        $page = WebPage::findOrFail($id);
        if (!$page->is_fixed) {
            // Falls ein Header-Bild existiert, löschen
            if ($page->header_image) {
                $this->deleteImageViaMediaController($page->header_image);
            }
            $page->delete();
        }
    }

    private function resetForm()
    {
        $this->editingId = null;
        $this->title = $this->slug = $this->meta_title = $this->meta_description = $this->meta_keywords = '';
        $this->canonical_url = $this->robots_meta = $this->og_title = $this->og_description = '';
        $this->custom_css = $this->custom_js = '';
        $this->custom_meta = [];
        $this->icon = null;
        $this->header_image = null;
        $this->header_image_url = null;
        $this->new_header_image = null;
        $this->is_active = true;
        $this->published_from = $this->published_until = null;
        $this->page = null;
    }

    protected function resizeImage($file, int $maxKb = 700): UploadedFile
    {
        $targetBytes = $maxKb * 1024;
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        // Bild laden
        $image = Image::read($file->getRealPath());

        // 1) Auf sinnvolle Maximalbreite runterskalieren (Proportionen bleiben)
        $maxWidth = 1920;
        if ($image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        // 2) Versuchsparameter
        $quality = 85;       // Start-Qualität
        $minQuality = 45;    // Nicht darunter gehen
        $attempts  = 0;
        $maxAttempts = 10;

        // Wir probieren erst WebP (meist kleiner) und fallen bei Bedarf auf JPEG zurück
        $useWebp = true;

        do {
            // Bei weiteren Versuchen, wenn noch zu groß: Qualität senken
            if ($attempts > 0) {
                $quality -= 5;
                if ($quality < $minQuality) {
                    // Wenn Qualität zu niedrig, noch etwas breiter runterskalieren (90 %)
                    $quality = 80;
                    $image->scaleDown(width: max(600, (int)round($image->width() * 0.9)));
                }
            }

            // Encode
            $encoded = $useWebp
                ? $image->toWebp($quality)
                : $image->toJpeg($quality); // JPEG strippt EXIF -> spart Bytes

            // Wenn WebP aus irgendeinem Grund größer bleibt, fallback zu JPEG
            if ($useWebp && strlen($encoded) > $targetBytes && $attempts >= 3) {
                $useWebp = false;
                $quality = 85; // reset für JPEG
                $attempts++;
                continue;
            }

            $attempts++;
        } while (strlen($encoded) > $targetBytes && $attempts < $maxAttempts);

        // Finales Format/MIME bestimmen
        $extension = $useWebp ? 'webp' : 'jpg';
        $mime = $useWebp ? 'image/webp' : 'image/jpeg';

        // In temporäre Datei schreiben
        $filename = 'header_' . uniqid() . '.' . $extension;
        $tmpPath  = $tmpDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($tmpPath, $encoded);

        // Als UploadedFile zurückgeben (test mode = true, d. h. keine echte HTTP-Uploadprüfung)
        return new UploadedFile($tmpPath, $filename, $mime, null, true);
    }
    
    protected function uploadImageViaMediaController($file)
    {
        // Temporäres Request-Objekt mit dem File als 'file'
        $request = Request::create('/admin/media/upload', 'POST', ['visibility' => 'public'], [], ['file' => $file]);

        // MediaController manuell instanziieren und aufrufen
        $controller = new MediaController();
        $response = $controller->store($request);

        if (method_exists($response, 'getData')) {
            return $response->getData(true)['path'] ?? '';
        }

        throw new \Exception('Upload fehlgeschlagen.');
    }



    protected static function deleteImageViaMediaController($path)
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

    public function removeNewHeaderImage()
    {
        $this->new_header_image = null;
    }

    public function removeHeaderImage()
    {
        if ($this->header_image) {
            $this->deleteImageViaMediaController($this->header_image);
            $this->header_image = null;
            $this->header_image_url = null;
        }
    }


    public function render()
    {
        return view('livewire.admin.cms.webpages.webpages-list', [
            'fixedPages' => WebPage::where('is_fixed', true)->paginate(10),
            'customPages' => WebPage::where('is_fixed', false)->paginate(10),
        ]);
    }
}
