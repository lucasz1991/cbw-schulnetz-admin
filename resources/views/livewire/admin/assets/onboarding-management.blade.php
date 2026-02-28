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
        activeTab: 'content',
        previewUrl: null,
        previewKind: null,

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
        this.cleanupPreviewUrl();

        // optional: signature/input reset, damit gleicher File-Name wieder sauber verarbeitet wird
        this._lastSig = null;

        // optional: input leeren (falls moeglich)
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
            this.cleanupPreviewUrl();

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
            this.metaText = `${file.name} - ${mb} MB`;

            if (isPdf) {
                this.previewUrl = URL.createObjectURL(file);
                this.previewKind = 'pdf';
                this.showPreview = true;
                return;
            }

            this.previewUrl = URL.createObjectURL(file);
            this.previewKind = isMp4 ? 'video' : null;
            this.showPreview = isMp4;

            // Dauer fuer Audio/Video (auch WAV speichern, aber Preview bleibt aus)
            if (isMp4 || isWav || file.type.startsWith('audio/') || file.type.startsWith('video/')) {
                const d = await this.getDuration(file, isWav ? 'audio' : 'video');
                this.durationSeconds = d ? Math.round(d) : null;
                this.$wire.set('duration_seconds', this.durationSeconds);
            }

            // Thumbnail nur fuer Video
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

        cleanupPreviewUrl() {
            if (this.previewUrl) {
                try { URL.revokeObjectURL(this.previewUrl); } catch (e) {}
            }
            this.previewUrl = null;
            this.previewKind = null;
        }
    }"
    x-effect="if (previewUrl) { showPreview = true; }"
    x-init="init()"
    x-on:onboarding:meta-reset.window="resetUi()"
    wire:key="onboarding-management-root"
>
    <div>
        <div class="rounded-2xl border border-slate-200 bg-gradient-to-r from-white to-slate-50 shadow-sm  px-5 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-secondary text-white grid place-items-center">
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
                        class="inline-flex items-center gap-2 rounded-xl bg-secondary px-4 py-2 text-sm font-semibold text-white hover:bg-secondary-dark focus:outline-none focus:ring-2 focus:ring-gray-900/30"
                    >
                        <i class="fas fa-plus text-xs"></i>
                        Neue Datei
                    </button>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="mt-6">
            <div class="overflow-x-auto bg-white  rounded-2xl shadow-lg">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold text-gray-600">
                            <th class="px-4 py-3">Titel</th>
                            <th class="px-4 py-3">Zielgruppe</th>
                            <th class="px-4 py-3">Status</th>
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
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ ucfirst(data_get($video->settings, 'type', '')) ?: 'Keine Zielgruppe' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    @if($video->is_active)
                                        Aktiv
                                    @else
                                        Inaktiv
                                    @endif
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
                                                Loeschen
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

            <div class="mt-4">
                {{ $videos->links() }}
            </div>
        </div>
    </div>

    {{-- Modal (Form + Drop-Zone) --}}
    <x-dialog-modal wire:model="showModal" maxWidth="3xl">
        <x-slot name="title">
            {{ $editingId ? 'Onboarding-Content bearbeiten' : 'Onboarding-Content anlegen' }}
        </x-slot>

        <x-slot name="content">
            <div class="mt-2 space-y-6">
                <div class="bg-gray-50 rounded-lg p-1">
                    <div class="flex w-full">
                        <button
                            type="button"
                            @click="activeTab = 'content'"
                            class="flex-1 px-5 py-3 text-sm font-semibold rounded-md transition-all"
                            :class="activeTab === 'content' ? 'bg-secondary/90 text-white shadow-sm hover:bg-secondary' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        >
                            Inhalt
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'settings'"
                            class="flex-1 px-5 py-3 text-sm font-semibold rounded-md transition-all"
                            :class="activeTab === 'settings' ? 'bg-secondary/90 text-white shadow-sm hover:bg-secondary' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        >
                            Einstellungen
                        </button>
                    </div>
                </div>

                <div x-show="activeTab === 'content'" x-cloak class="space-y-6">
                    <div class="space-y-4">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-800">Titel *</label>
                            <input type="text" id="title" wire:model="title" class="mt-1 block w-full border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-200 focus:border-blue-300">
                            <x-input-error for="title" class="mt-2" />
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-800">Beschreibung</label>
                            <textarea id="description" rows="3" wire:model="description" class="mt-1 block w-full border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-200 focus:border-blue-300"></textarea>
                            <x-input-error for="description" class="mt-2" />
                        </div>
                    </div>

                    {{-- Hidden inputs for browser computed meta --}}
                    <input type="hidden" wire:model="duration_seconds">
                    <input type="hidden" wire:model="thumbnailDataUrl">

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Datei hochladen</p>
                                <p class="text-xs text-gray-500">MP4, WAV oder PDF</p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($editingId && $existingVideo && !$showUploadField)
                                    <button
                                        type="button"
                                        wire:click="startReplacingVideo"
                                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100"
                                    >
                                        <i class="fas fa-refresh text-xs"></i>
                                        Datei ersetzen
                                    </button>
                                @elseif(!$showUploadField)
                                    <button
                                        type="button"
                                        wire:click="startReplacingVideo"
                                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100"
                                    >
                                        <i class="fas fa-refresh text-xs"></i>
                                        Andere Datei waehlen
                                    </button>
                                @endif
                            </div>
                        </div>

                        @if($existingVideo)
                            @php
                                $existingIsPdf = isset($existingVideo['mime']) && str_contains(strtolower($existingVideo['mime']), 'pdf');
                            @endphp
                            <div class="rounded-xl bg-gray-50 p-4 space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-700">
                                    <div class="space-y-1">
                                        <div class="font-semibold text-gray-900">{{ $existingVideo['name'] }}</div>
                                        <div class="text-gray-600">
                                            {{ $existingVideo['mime'] ?? 'unbekannt' }}
                                            @if($existingVideo['size_mb']) &middot; {{ $existingVideo['size_mb'] }} MB @endif
                                        </div>
                                    </div>
                                    <div class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100">Aktuelle Datei</div>
                                </div>
                                <div class="overflow-hidden rounded-lg ring-1 ring-gray-200 bg-black/80">
                                    @if($existingVideo['url'])
                                        @if($existingIsPdf)
                                            <embed src="{{ $existingVideo['url'] }}" type="{{ $existingVideo['mime'] ?? 'application/pdf' }}" class="h-72 w-full object-contain bg-white" />
                                        @else
                                            <video src="{{ $existingVideo['url'] }}" class="h-72 w-full object-cover" controls preload="metadata"></video>
                                        @endif
                                    @else
                                        <div class="grid h-72 place-items-center text-xs text-gray-300">Keine Vorschau</div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($showUploadField)
                            <div class="mt-1" x-ref="dropzoneRoot">
                                <x-ui.filepool.drop-zone
                                    :model="'fileUploads'"
                                    mode="single"
                                    label="Datei auswählen"
                                    acceptedFiles=".mp4,.wav,.pdf"
                                    :maxFilesize="100"
                                />
                            </div>
                            <x-input-error for="fileUploads.*" class="mt-2" />
                        @endif

                        {{-- Preview: bei neuem Upload (Video/PDF) --}}
                        <div
                            class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-3 space-y-3"
                            x-show="showPreview && (isVideo || isPdf)"
                            x-cloak
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-xs text-gray-600">
                                    <span x-text="metaText"></span>
                                </div>

                            </div>

                            <div class="text-[11px] text-gray-500" x-show="durationSeconds !== null && isVideo" x-cloak>
                                Dauer: <span class="font-semibold" x-text="durationSeconds"></span> Sekunden
                            </div>

                            <template x-if="isVideo">
                                <div class="space-y-2">
                                    <video x-show="previewUrl" :src="previewUrl" class="w-full h-64 rounded-lg border border-gray-200" controls preload="metadata"></video>
                                    <div class="flex items-start gap-3" x-show="thumbDataUrl" x-cloak>
                                        <img :src="thumbDataUrl" class="h-16 w-28 rounded-lg object-cover ring-1 ring-gray-200" alt="">
                                        <div class="text-xs text-gray-600">
                                            <div class="font-semibold text-gray-800">Thumbnail Preview</div>
                                            <div>Wird beim Speichern als File (type: onboarding_video_thumbnail) gespeichert.</div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <template x-if="isPdf">
                                <div>
                                    <embed x-show="previewUrl" :src="previewUrl" type="application/pdf" class="h-72 w-full rounded-lg border border-gray-200 bg-white" />
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div x-show="activeTab === 'settings'" x-cloak class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-800">Status</label>
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                                <span class="text-sm text-gray-700">Aktiv</span>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="is_active" class="sr-only peer">
                                    <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-800">Zielgruppe</label>
                            <select wire:model="type" class="mt-1 block w-full border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-200 focus:border-blue-300 bg-white">
                                <option value="">Keine Zielgruppe</option>
                                <option value="weiterbildung">Weiterbildung</option>
                                <option value="umschulung">Umschulung</option>
                            </select>
                            <x-input-error for="type" class="mt-2" />
                        </div>
                    </div>

                    <!-- <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label for="category" class="block text-sm font-medium text-gray-800">Kategorie</label>
                            <input type="text" id="category" wiremodel="category" class="mt-1 block w-full border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-200 focus:border-blue-300" placeholder="z. B. Onboarding, HR">
                            <xinput-error for="category" class="mt-2" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-800">Seiten</label>
                            <select wiremodel="assigned_pages" multiple class="mt-1 block w-full border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-200 focus:border-blue-300 bg-white">
                                <option value="all">Alle Seiten</option>
                                isset($webpages)
                                    foreach($webpages as $page)
                                        <option value="page->slug  $page->title </option>
                                    endforeach
                                endisset
                            </select>
                            <xinput-error for="assigned_pages" class="mt-1" />
                        </div>
                    </div> -->

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="valid_from" class="block text-sm font-medium text-gray-800">Gueltig ab</label>
                            <input type="datetime-local" id="valid_from" wire:model="valid_from" class="mt-1 block w-full border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-200 focus:border-blue-300">
                            <x-input-error for="valid_from" class="mt-2" />
                        </div>

                        <div>
                            <label for="valid_until" class="block text-sm font-medium text-gray-800">Gueltig bis</label>
                            <input type="datetime-local" id="valid_until" wire:model="valid_until" class="mt-1 block w-full border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-200 focus:border-blue-300">
                            <x-input-error for="valid_until" class="mt-2" />
                        </div>
                    </div>
                </div>
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-ui.buttons.button-basic wire:click="closeModal" :size="'sm'" :mode="'secondary'" wire:loading.attr="disabled">
                <i class="fas fa-times mr-2"></i>
                Abbrechen
            </x-ui.buttons.button-basic>

            <x-ui.buttons.button-basic wire:click="save" :size="'sm'" :mode="'primary'" wire:loading.attr="disabled" class="ml-2">
                <i class="fas fa-save mr-2"></i>
                Speichern
            </x-ui.buttons.button-basic>
        </x-slot>
    </x-dialog-modal>
</div>
