<div
  x-data="{ bust: 0 }"
  x-on:filepool-preview.window="$wire.openWith($event.detail.id); bust = Date.now()"
  x-on:filepreview:open.window="
        $wire.openWithPath(
            $event.detail.disk ?? 'local',
            $event.detail.path ?? '',
            $event.detail.name ?? null,
            $event.detail.deleteOnClose ?? true
        );
        bust = Date.now();
  "
>
  <x-dialog-modal wire:model="open" :maxWidth="'4xl'">

    <x-slot name="title">
      @php
        // ---------------------------------------------
        // MODE DETECTION
        // ---------------------------------------------
        $hasModelFile = isset($file) && $file;
        $hasTemp      = isset($dataUrl) && $dataUrl;

        // ---------------------------------------------
        // MODEL FILE META
        // ---------------------------------------------
        $mimeModel = $hasModelFile ? ($file->mime_type ?? '') : '';
        $isImageModel = $mimeModel && str_starts_with($mimeModel, 'image/');
        $isVideoModel = $mimeModel && str_starts_with($mimeModel, 'video/');
        $isAudioModel = $mimeModel && str_starts_with($mimeModel, 'audio/');
        $isPdfModel   = $mimeModel && str_contains($mimeModel, 'pdf');
        $isTextModel  = $mimeModel && str_contains($mimeModel, 'text');

        $tempUrlModel = $hasModelFile ? ($this->url ?? ($file->getEphemeralPublicUrl() ?? null)) : null;

        $printUrl = '';
        if ($hasModelFile && ($isPdfModel || $isTextModel || $isImageModel)) {
          $printUrl = $tempUrlModel ?: '';
        }

        // ---------------------------------------------
        // TEMP FILE META (Path-Mode / DataURL)
        // ---------------------------------------------
        $mimeTemp = $hasTemp ? ($mime ?? '') : '';
        $isPdfTemp = $mimeTemp === 'application/pdf';
        $isImageTemp = $mimeTemp && str_starts_with($mimeTemp, 'image/');

        $titleName = $hasModelFile
          ? ($file->name_with_extension ?? $file->name)
          : ($name ?? 'Export');

        $subtitleMime = $hasModelFile
          ? ($file->getMimeTypeForHumans() ?? $mimeModel)
          : ($mimeTemp ?: 'application/pdf');

        $sizeText = $hasModelFile
          ? ($file?->sizeFormatted ?? '')
          : ($this->tempSizeFormatted ?? '');
      @endphp

      <div class="flex flex-wrap sm:flex-nowrap items-start sm:items-center justify-between gap-2">
        @if(($hasModelFile || $hasTemp) && $open)
          {{-- Left --}}
          <div class="min-w-0 flex-1">
            <div class="text-sm text-gray-800 mb-1 truncate" title="{{ $titleName }}">
              {{ $titleName }}
            </div>
            <div class="text-xs text-gray-500 mb-1 truncate" title="{{ $subtitleMime }}">
              <span class="block truncate">{{ $subtitleMime }}</span>
            </div>
            @if($sizeText)
              <div class="text-xs text-gray-500">
                <span>{{ $sizeText }}</span>
              </div>
            @endif
          </div>

          {{-- Right actions --}}
          <div class="shrink-0 mt-2 sm:mt-0 flex items-center gap-2">
            {{-- Download (StreamedResponse) --}}
            <button
              wire:click="download"
              class="inline-flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-500 rounded-full p-2 focus:outline-none focus:ring focus:ring-gray-300"
              title="Download"
            >
              <i class="fas fa-download w-4 h-4 leading-none"></i>
              <span class="sr-only">Download</span>
            </button>

            {{-- Print only for Model-Mode where URL exists --}}
            @if($printUrl !== '')
              <a
                href="{{ $printUrl }}"
                target="_blank" rel="noopener"
                class="inline-flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-500 rounded-full p-2 focus:outline-none focus:ring focus:ring-gray-300"
                title="Drucken"
              >
                <i class="fas fa-print w-4 h-4 leading-none"></i>
                <span class="sr-only">Drucken</span>
              </a>
            @endif

            {{-- Close --}}
            <button
              wire:click="close"
              class="inline-flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-500 rounded-full p-2 focus:outline-none focus:ring focus:ring-gray-300"
              title="Schließen"
            >
              <i class="fas fa-times w-4 h-4 leading-none"></i>
              <span class="sr-only">Schließen</span>
            </button>
          </div>
        @else
          <div class="min-w-0 flex-1">
            <span class="font-semibold">Dateivorschau</span>
          </div>
          <div class="shrink-0">
            <button
              wire:click="close"
              class="inline-flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-full p-2 focus:outline-none focus:ring focus:ring-gray-300"
              title="Schließen"
            >
              <i class="fas fa-times w-4 h-4 leading-none"></i>
              <span class="sr-only">Schließen</span>
            </button>
          </div>
        @endif
      </div>
    </x-slot>

    <x-slot name="content">
      @if(($hasModelFile || $hasTemp) && $open)

        <div class="rounded-md border overflow-hidden bg-white">
          {{-- -------------------------------
               MODEL FILE MODE
               ------------------------------- --}}
          @if($hasModelFile)

            {{-- Image --}}
            @if($isImageModel)
              <div class="flex justify-center items-center bg-gray-100 min-h-[200px]">
                <img class="block w-auto h-auto"
                     src="{{ $tempUrlModel }}?b={{ now()->timestamp }}"
                     alt="{{ $titleName }}" />
              </div>

            {{-- Video --}}
            @elseif($isVideoModel)
              <div>
                <video class="block w-full h-[75vh] min-h-[420px]"
                       controls
                       src="{{ $tempUrlModel }}?b={{ now()->timestamp }}">
                </video>
              </div>

            {{-- Audio --}}
            @elseif($isAudioModel)
              <div class="p-4">
                <audio class="w-full" controls src="{{ $tempUrlModel }}?b={{ now()->timestamp }}"></audio>
              </div>

            {{-- PDF / Text --}}
            @elseif($isPdfModel || $isTextModel)
              <div>
                <iframe
                  key="file-preview-model-{{ $file->id }}-{{ $file->updated_at?->timestamp ?? $file->id }}"
                  class="w-full min-h-[60vh] max-h-[70vh]"
                  src="{{ $tempUrlModel }}?b={{ now()->timestamp }}"
                ></iframe>
              </div>

            {{-- Fallback --}}
            @else
              <div class="p-6 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                  <img class="w-10 h-10 object-contain"
                       src="{{ $file->icon_or_thumbnail }}"
                       alt="Datei-Icon">
                  <div class="min-w-0">
                    <div class="font-medium text-gray-900 truncate">{{ $titleName }}</div>
                    @if($mimeModel)
                      <div class="text-xs text-gray-500 mt-0.5">{{ $mimeModel }}</div>
                    @endif
                    <div class="text-xs text-gray-500">
                      Keine Inline-Vorschau verfügbar. Bitte im neuen Tab öffnen.
                    </div>
                  </div>
                </div>
              </div>
            @endif

          {{-- -------------------------------
               TEMP PATH MODE (Data URL)
               ------------------------------- --}}
          @else

            {{-- PDF --}}
            @if($isPdfTemp)
              <div>
                <iframe
                  key="file-preview-temp-{{ md5($dataUrl) }}"
                  class="w-full min-h-[60vh] max-h-[70vh]"
                  src="{{ $dataUrl }}"
                ></iframe>
              </div>

            {{-- Image --}}
            @elseif($isImageTemp)
              <div class="flex justify-center items-center bg-gray-100 min-h-[200px]">
                <img class="block w-auto h-auto"
                     src="{{ $dataUrl }}"
                     alt="{{ $titleName }}" />
              </div>

            {{-- Fallback --}}
            @else
              <div class="p-6 text-sm text-gray-600">
                Keine Vorschau verfügbar.
              </div>
            @endif

          @endif
        </div>

      @else
        <p class="text-sm text-gray-600">Keine Datei ausgewählt.</p>
      @endif
    </x-slot>

    <x-slot name="footer">
      <div class="flex items-center gap-2">
        @if(isset($file) && $file)
          <x-ui.buttons.button-basic
            :mode="'basic'"
            href="{{ $file->getEphemeralPublicUrl() }}"
            target="blank"
            rel="noopener noreferrer"
            :size="'sm'"
          >
            <i class="fas fa-external-link-alt mr-2"></i>
            In neuem Tab öffnen
          </x-ui.buttons.button-basic>
        @endif

        <x-ui.buttons.button-basic
          :mode="'basic'"
          wire:click="close"
          :size="'sm'"
          wire:loading.attr="disabled"
          class="disabled:opacity-60 disabled:cursor-wait"
        >
          <i class="fas fa-times mr-2 " wire:loading.remove></i>
          <i class="fal fa-spinner fa-spin text-[14px] text-blue-500 mr-2 " wire:loading ></i>
          Schließen
        </x-ui.buttons.button-basic>
      </div>
    </x-slot>

  </x-dialog-modal>
</div>
