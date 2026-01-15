<?php

namespace App\Livewire\Admin\Assets;

use App\Models\OnboardingVideo;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class OnboardingManagement extends Component
{
    use WithPagination, WithFileUploads;

    public int $perPage = 12;

    /** Modal */
    public bool $showModal = false;
    public ?int $editingId = null;

    /** Form */
    public string $title = '';
    public ?string $description = null;

    public bool $is_active = true;

    public ?string $valid_from = null;  // datetime-local
    public ?string $valid_until = null; // datetime-local

    /** Browser computed meta */
    public ?int $duration_seconds = null;
    public ?string $thumbnailDataUrl = null; // data:image/jpeg;base64,...

    /**
     * Uploads als Array (Drop-Zone), logisch aber max 1 Datei.
     * Erlaubt: mp4, wav, pdf
     */
    public array $fileUploads = [];

    public function updatedFileUploads(): void
    {
        // nur letzte behalten
        if (count($this->fileUploads) > 1) {
            $this->fileUploads = [end($this->fileUploads)];
        }

        // Meta wird im Browser gesetzt -> hier nur optional resetten, falls Upload geleert wird
        if (empty($this->fileUploads)) {
            $this->duration_seconds = null;
            $this->thumbnailDataUrl = null;
        }
    }

    public function create(): void
    {
        $this->resetErrorBag();
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetErrorBag();
        $this->resetForm();
        $video = OnboardingVideo::withTrashed()->findOrFail($id);

        $this->editingId = $video->id;

        $this->title = (string) $video->title;
        $this->description = $video->description;

        $this->is_active = (bool) $video->is_active;

        $this->valid_from = $video->valid_from?->format('Y-m-d\TH:i');
        $this->valid_until = $video->valid_until?->format('Y-m-d\TH:i');

        // bestehende Dauer behalten, Thumbnail wird nur ersetzt wenn neues Video hochgeladen wird
        $this->duration_seconds = $video->duration_seconds;
        $this->thumbnailDataUrl = null;

        // im Edit: neuer Upload optional
        
        $this->fileUploads = [];

        $this->showModal = true;
    }

    public function resetForm(): void
    {
        $this->dispatch('filepool:saved'); 
        $this->editingId = null;

        $this->title = '';
        $this->description = null;

        $this->is_active = true;

        $this->valid_from = null;
        $this->valid_until = null;

        $this->duration_seconds = null;
        $this->thumbnailDataUrl = null;

        $this->fileUploads = [];
        $this->dispatch('onboarding:meta-reset');

    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetErrorBag();
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'is_active' => ['boolean'],

            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],

            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'thumbnailDataUrl' => ['nullable', 'string'],

            // Create: 1 Datei required; Edit: optional
            'fileUploads' => [$this->editingId ? 'nullable' : 'required', 'array', 'max:1'],
            'fileUploads.*' => ['file', 'mimes:mp4,wav,pdf', 'max:51200'],
        ];
    }

    protected function messages(): array
    {
        return [
            'valid_until.after_or_equal' => 'valid_until muss nach valid_from liegen.',

            'fileUploads.required' => 'Bitte genau eine Datei hochladen.',
            'fileUploads.array' => 'Upload ist ungültig.',
            'fileUploads.max' => 'Bitte nur eine Datei hochladen.',

            'fileUploads.*.mimes' => 'Nur .mp4, .wav oder .pdf Dateien sind erlaubt.',
            'fileUploads.*.max' => 'Die Datei überschreitet die maximale Größe von 50 MB.',
        ];
    }

    public function save(): void
    {
        // final absichern: max 1
        if (count($this->fileUploads) > 1) {
            $this->fileUploads = [end($this->fileUploads)];
        }

        $this->validate();

        $payload = [
            'title' => $this->title,
            'description' => $this->description,

            'is_active' => $this->is_active,

            'valid_from' => $this->valid_from,
            'valid_until' => $this->valid_until,

            // Dauer kommt vom Browser (für mp4/wav), bei pdf = null
            'duration_seconds' => $this->duration_seconds,
        ];

        // Create + sort_order = nächster Wert
        if (!$this->editingId) {
            $max = (int) (OnboardingVideo::max('sort_order') ?? 0);
            $payload['sort_order'] = $max + 1;
            $payload['created_by'] = auth()->id();

            $video = OnboardingVideo::create($payload);
            $this->editingId = $video->id;
        } else {
            $video = OnboardingVideo::withTrashed()->findOrFail($this->editingId);
            $video->fill($payload)->save();
        }

        // Datei speichern (genau 1; beim Edit optional)
        if (!empty($this->fileUploads)) {
            $upload = $this->fileUploads[0];

            // garantieren: nur 1 Asset-File pro OnboardingVideo
            $video->files()->where('type', 'onboarding_video')->get()->each(fn ($f) => $f->delete());

            $original = $upload->getClientOriginalName();
            $path = $upload->store('uploads/onboarding/assets', 'public');

            $mime = Storage::disk('public')->mimeType($path) ?? $upload->getClientMimeType();

            $video->files()->create([
                'user_id' => auth()->id(),
                'name' => $original,
                'path' => $path,
                'mime_type' => $mime,
                'type' => 'onboarding_video',
                'size' => $upload->getSize(),
                'expires_at' => null,
            ]);
        }

        // Thumbnail speichern (nur wenn Browser eins geliefert hat)
        if ($this->thumbnailDataUrl && str_starts_with($this->thumbnailDataUrl, 'data:image/')) {
            $video->files()->where('type', 'onboarding_video_thumbnail')->get()->each(fn ($f) => $f->delete());

            [$meta, $data] = explode(',', $this->thumbnailDataUrl, 2);
            $bin = base64_decode($data);

            if ($bin !== false && strlen($bin) > 0) {
                $filename = 'thumb_' . $video->id . '_' . time() . '.jpg';
                $thumbPath = 'uploads/onboarding/thumbnails/' . $filename;

                Storage::disk('public')->put($thumbPath, $bin);

                $video->files()->create([
                    'user_id' => auth()->id(),
                    'name' => $filename,
                    'path' => $thumbPath,
                    'mime_type' => 'image/jpeg',
                    'type' => 'onboarding_video_thumbnail',
                    'size' => strlen($bin),
                    'expires_at' => null,
                ]);
            }
        }

        // Reset transient inputs
        $this->fileUploads = [];
        $this->thumbnailDataUrl = null;

        $this->closeModal();
        $this->dispatch('showAlert', 'Onboarding-Asset gespeichert.', 'success');
        $this->resetPage();
    }

    #[On('orderOnboardingVideo')]
    public function orderOnboardingVideo($item, $position): void
    {
        $id = null;

        if (is_array($item) && isset($item['id'])) $id = (int) $item['id'];
        elseif (is_object($item) && isset($item->id)) $id = (int) $item->id;
        elseif (is_numeric($item)) $id = (int) $item;

        if (!$id) return;

        $position = (int) $position;
        if ($position < 0) $position = 0;

        $ids = OnboardingVideo::query()
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $currentIndex = array_search($id, $ids, true);
        if ($currentIndex === false) return;

        array_splice($ids, $currentIndex, 1);

        $maxIndex = count($ids);
        if ($position > $maxIndex) $position = $maxIndex;

        array_splice($ids, $position, 0, [$id]);

        foreach ($ids as $i => $vid) {
            OnboardingVideo::where('id', $vid)->update(['sort_order' => $i + 1]);
        }

        $this->dispatch('showAlert', 'Reihenfolge aktualisiert.', 'success');
    }

    public function toggleActive(int $id): void
    {
        $video = OnboardingVideo::withTrashed()->findOrFail($id);
        if ($video->trashed()) return;

        $video->is_active = !$video->is_active;
        $video->save();
    }

    public function softDelete(int $id): void
    {
        $video = OnboardingVideo::findOrFail($id);
        $video->delete();
        $this->dispatch('showAlert', 'Video gelöscht (Soft-Delete).', 'success');
        $this->resetPage();
    }

    public function restore(int $id): void
    {
        $video = OnboardingVideo::withTrashed()->findOrFail($id);
        $video->restore();
        $this->dispatch('showAlert', 'Video wiederhergestellt.', 'success');
    }

    public function forceDelete(int $id): void
    {
        $video = OnboardingVideo::withTrashed()->findOrFail($id);

        // Files aufräumen
        $video->files()->get()->each(fn ($f) => $f->delete());

        $video->forceDelete();
        $this->dispatch('showAlert', 'Video endgültig gelöscht.', 'success');
        $this->resetPage();
    }

    public function render()
    {
        $videos = OnboardingVideo::query()
            ->withTrashed()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage);

        return view('livewire.admin.assets.onboarding-management', [
            'videos' => $videos,
        ])->layout('layouts.master');
    }
}
