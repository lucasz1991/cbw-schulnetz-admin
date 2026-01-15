<div
    class="space-y-6"
    x-data="{
        /* -------------------------------------------------
         * UI state
         * ------------------------------------------------- */
        metaText: '',
        showPreview: false,
        isVideo: false,
        isPdf: false,
        thumbDataUrl: null,
        durationSeconds: null,

        /* -------------------------------------------------
         * internals
         * ------------------------------------------------- */
        _input: null,
        _bound: false,
        _lastSig: null,

        init() {
            if (this._bound) return;
            this._bound = true;

            const bindNow = () => this.bindFileInput();

            queueMicrotask(bindNow);

            if (window.Livewire?.hook) {
                window.Livewire.hook('message.processed', () => queueMicrotask(bindNow));
            }
        },

        resetUi() {
        this.metaText = '';
        this.showPreview = false;
        this.isVideo = false;
        this.isPdf = false;
        this.thumbDataUrl = null;
        this.durationSeconds = null;

        // optional: signature/input reset, damit gleicher File-Name wieder sauber verarbeitet wird
        this._lastSig = null;

        // optional: input leeren (falls möglich)
        if (this._input) {
            try { this._input.value = ''; } catch (e) {}
        }
    },

        bindFileInput() {
            const root = this.$refs.dropzoneRoot ?? this.$root;
            const input = root ? root.querySelector('input[type=file]') : null;
            if (!input) return;

            if (this._input === input) return;

            if (this._input) this._input.onchange = null;

            this._input = input;

            input.onchange = async (e) => {
                const file = e.target?.files?.[0] ?? null;
                if (!file) return;
                await this.process(file);
            };
        },

        async process(file) {
            const sig = `${file.name}:${file.size}:${file.lastModified}`;
            if (this._lastSig === sig) return;
            this._lastSig = sig;

            // reset UI
            this.metaText = '';
            this.showPreview = false;
            this.isVideo = false;
            this.isPdf = false;
            this.thumbDataUrl = null;
            this.durationSeconds = null;

            // reset Livewire fields
            this.$wire.set('thumbnailDataUrl', null);
            this.$wire.set('duration_seconds', null);

            const name = (file.name || '').toLowerCase();
            const isPdf = file.type === 'application/pdf' || name.endsWith('.pdf');
            const isMp4 =
                name.endsWith('.mp4') ||
                file.type === 'video/mp4' ||
                file.type.startsWith('video/');
            const isWav =
                name.endsWith('.wav') ||
                file.type === 'audio/wav' ||
                file.type.startsWith('audio/');

            this.isPdf = isPdf;
            this.isVideo = isMp4;

            const mb = (file.size / (1024 * 1024)).toFixed(2);
            this.metaText = `${file.name} • ${mb} MB`;

            // Preview nur für Videos
            this.showPreview = isMp4;

            if (isPdf) return;

            // Dauer für Audio/Video (auch WAV speichern, aber Preview bleibt aus)
            if (isMp4 || isWav || file.type.startsWith('audio/') || file.type.startsWith('video/')) {
                const d = await this.getDuration(file, isWav ? 'audio' : 'video');
                this.durationSeconds = d ? Math.round(d) : null;
                this.$wire.set('duration_seconds', this.durationSeconds);
            }

            // Thumbnail nur für Video
            if (isMp4) {
                const thumb = await this.makeThumb(file);
                if (thumb) {
                    this.thumbDataUrl = thumb;
                    this.$wire.set('thumbnailDataUrl', thumb);
                }
            }
        },

        getDuration(file, kind = 'video') {
            return new Promise((resolve) => {
                const el = document.createElement(kind === 'audio' ? 'audio' : 'video');
                el.preload = 'metadata';

                const url = URL.createObjectURL(file);

                const cleanup = () => {
                    try { URL.revokeObjectURL(url); } catch (e) {}
                    el.src = '';
                };

                el.onloadedmetadata = () => {
                    const d = el.duration;
                    cleanup();
                    resolve(Number.isFinite(d) ? d : null);
                };

                el.onerror = () => {
                    cleanup();
                    resolve(null);
                };

                el.src = url;
            });
        },

        async makeThumb(file) {
            const video = document.createElement('video');
            video.muted = true;
            video.playsInline = true;
            video.preload = 'auto';

            const url = URL.createObjectURL(file);

            const cleanup = () => {
                try { URL.revokeObjectURL(url); } catch (e) {}
                video.src = '';
            };

            try {
                await new Promise((res, rej) => {
                    video.onloadedmetadata = () => res();
                    video.onerror = () => rej(new Error('metadata error'));
                    video.src = url;
                });

                // nicht 0s, oft schwarzer frame
                const target = Math.min(1, Math.max(0.1, (video.duration || 0) * 0.1));

                await new Promise((res, rej) => {
                    let done = false;

                    const onSeeked = () => {
                        if (done) return;
                        done = true;
                        video.removeEventListener('seeked', onSeeked);
                        res();
                    };

                    video.addEventListener('seeked', onSeeked);
                    video.currentTime = Number.isFinite(target) ? target : 0;

                    setTimeout(() => {
                        if (done) return;
                        done = true;
                        video.removeEventListener('seeked', onSeeked);
                        rej(new Error('seek timeout'));
                    }, 4000);
                });

                const canvas = document.createElement('canvas');
                const vw = video.videoWidth || 1280;
                const vh = video.videoHeight || 720;

                const maxW = 640;
                const scale = vw > maxW ? (maxW / vw) : 1;

                canvas.width = Math.round(vw * scale);
                canvas.height = Math.round(vh * scale);

                const ctx = canvas.getContext('2d');
                if (!ctx) return null;

                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                return canvas.toDataURL('image/jpeg', 0.82);
            } catch (e) {
                return null;
            } finally {
                cleanup();
            }
        },
    }"
    x-init="init()"
    x-on:onboarding:meta-reset.window="resetUi()"
    wire:key="onboarding-management-root"
>
    <div>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-gray-900 text-white grid place-items-center">
                        <i class="fas fa-film text-sm"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-lg font-semibold text-gray-900 truncate">Onboarding</div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="create"
                    class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/30"
                >
                    <i class="fas fa-plus text-xs"></i>
                    Neues Video
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="mt-6 overflow-hidden rounded-2xl ring-1 ring-gray-200">
            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold text-gray-600">
                            <th class="px-4 py-3">Titel</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>

                    {{-- Drag & Drop Sort --}}
                    <tbody
                        class="divide-y divide-gray-200"
                        x-sort="$dispatch('orderOnboardingVideo', { item: $item, position: $position })"
                    >
                        @forelse($videos as $video)
                            <tr class="hover:bg-gray-50" x-sort:item="{{ $video }}">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-xl bg-gray-100 grid place-items-center text-gray-600">
                                            <i class="fas fa-grip-vertical text-xs"></i>
                                        </div>

                                        <div class="min-w-0 flex items-center">
                                            <div class="text-sm font-semibold text-gray-900 truncate mr-2">
                                                {{ $video->title }}
                                            </div>

                                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                                @if($video->trashed())
                                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">
                                                        Papierkorb
                                                    </span>
                                                @else
                                                    <button
                                                        type="button"
                                                        wire:click="toggleActive({{ $video->id }})"
                                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold
                                                            {{ $video->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700' }}"
                                                        title="Status umschalten"
                                                    >
                                                        {{ $video->is_active ? 'Aktiv' : 'Inaktiv' }}
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            wire:click="edit({{ $video->id }})"
                                            class="inline-flex items-center gap-2 rounded-xl bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-800 hover:bg-gray-200"
                                        >
                                            <i class="fas fa-pen text-xs"></i>
                                            Bearbeiten
                                        </button>

                                        @if($video->trashed())
                                            <button
                                                type="button"
                                                wire:click="restore({{ $video->id }})"
                                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500"
                                            >
                                                <i class="fas fa-undo text-xs"></i>
                                                Restore
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="forceDelete({{ $video->id }})"
                                                class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-500"
                                            >
                                                <i class="fas fa-trash text-xs"></i>
                                                Delete
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="softDelete({{ $video->id }})"
                                                class="inline-flex items-center gap-2 rounded-xl bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100"
                                            >
                                                <i class="fas fa-trash text-xs"></i>
                                                Löschen
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-10 text-center text-sm text-gray-500">
                                    Keine Videos vorhanden.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-4 py-4">
                {{ $videos->links() }}
            </div>
        </div>
    </div>

    {{-- Modal (Form + Drop-Zone) --}}
    <x-dialog-modal wire:model="showModal" maxWidth="3xl">
        <x-slot name="title">
            {{ $editingId ? 'Onboarding-Video bearbeiten' : 'Onboarding-Video anlegen' }}
        </x-slot>

        <x-slot name="content">
            <div class="mt-2 space-y-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Titel *</label>
                    <input type="text" id="title" wire:model="title" class="mt-1 block w-full border rounded px-4 py-2">
                    <x-input-error for="title" class="mt-2" />
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Beschreibung</label>
                    <textarea id="description" rows="4" wire:model="description" class="mt-1 block w-full border rounded px-4 py-2"></textarea>
                    <x-input-error for="description" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="valid_from" class="block text-sm font-medium text-gray-700">Gültig ab</label>
                        <input type="datetime-local" id="valid_from" wire:model="valid_from" class="mt-1 block w-full border rounded px-4 py-2">
                        <x-input-error for="valid_from" class="mt-2" />
                    </div>

                    <div>
                        <label for="valid_until" class="block text-sm font-medium text-gray-700">Gültig bis</label>
                        <input type="datetime-local" id="valid_until" wire:model="valid_until" class="mt-1 block w-full border rounded px-4 py-2">
                        <x-input-error for="valid_until" class="mt-2" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex items-center gap-4 mt-6 sm:mt-0">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="sr-only peer">
                            <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            <span class="ms-3 text-sm font-medium text-gray-900">Aktiv</span>
                        </label>
                    </div>
                </div>

                {{-- Hidden inputs for browser computed meta --}}
                <input type="hidden" wire:model="duration_seconds">
                <input type="hidden" wire:model="thumbnailDataUrl">

                <div class="mt-4">
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-medium text-gray-700">Datei Upload</label>
                        <span class="text-xs text-gray-500">Erlaubt: MP4, WAV, PDF</span>
                    </div>

                    <div class="mt-2" x-ref="dropzoneRoot">
                        <x-ui.filepool.drop-zone
                            :model="'fileUploads'"
                            :mode="'single'"
                            :label="'Datei auswählen'"
                            :acceptedFiles="'mp4,wav,pdf'"
                        />
                    </div>

                    <x-input-error for="fileUploads" class="mt-2" />
                    <x-input-error for="fileUploads.*" class="mt-2" />

                    {{-- Preview: NUR wenn Video gewählt --}}
                    <div
                        class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-3"
                        x-show="showPreview && isVideo"
                        x-cloak
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-xs text-gray-600">
                                <span class="font-semibold">Meta (Browser):</span>
                                <span x-text="metaText"></span>
                            </div>

                            <span
                                class="inline-flex items-center rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200"
                                x-show="isVideo"
                                x-cloak
                            >
                                Video (Thumbnail)
                            </span>
                        </div>

                        <div class="mt-2 text-[11px] text-gray-500" x-show="durationSeconds !== null" x-cloak>
                            Dauer: <span class="font-semibold" x-text="durationSeconds"></span> Sekunden
                        </div>

                        <div class="mt-3 flex items-start gap-3" x-show="thumbDataUrl" x-cloak>
                            <img :src="thumbDataUrl" class="h-16 w-28 rounded-lg object-cover ring-1 ring-gray-200" alt="">
                            <div class="text-xs text-gray-600">
                                <div class="font-semibold text-gray-800">Thumbnail Preview</div>
                                <div>Wird beim Speichern als File (type: onboarding_video_thumbnail) gespeichert.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="closeModal" wire:loading.attr="disabled">
                Abbrechen
            </x-secondary-button>

            <x-button wire:click="save" wire:loading.attr="disabled" class="ml-2">
                Speichern
            </x-button>
        </x-slot>
    </x-dialog-modal>
</div>
