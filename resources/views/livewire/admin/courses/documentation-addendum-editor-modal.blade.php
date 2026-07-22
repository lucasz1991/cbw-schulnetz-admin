<div>
    <x-dialog-modal wire:model="showModal" maxWidth="5xl">
        <x-slot name="title">
            <div>
                <div class="text-lg font-semibold text-slate-900">Dokumentationszusatz bearbeiten</div>
                <div class="mt-1 text-xs font-normal text-slate-500">
                    {{ $courseTitle }} · {{ $dayLabel }}
                </div>
            </div>
        </x-slot>

        <x-slot name="content">
            @if($courseDayId)
                <div class="space-y-5">
                    <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Original-Dokumentation</h3>
                            <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-500">
                                <i class="fal fa-lock"></i> Schreibgeschützt
                            </span>
                        </div>
                        @if(trim(strip_tags($originalNotesHtml)) !== '')
                            <div class="prose prose-sm max-w-none text-slate-700">
                                {!! $originalNotesHtml !!}
                            </div>
                        @else
                            <p class="text-sm italic text-slate-400">Keine Original-Dokumentation hinterlegt.</p>
                        @endif
                    </section>

                    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                            <h3 class="text-sm font-semibold text-slate-800">Optionaler Dokumentationszusatz</h3>
                            <p class="mt-1 text-xs text-slate-500">Nur veröffentlichte Zusätze werden Teilnehmern, im Berichtsheft und im Dokumentations-PDF angezeigt.</p>
                        </div>
                        <div class="p-1">
                            <x-ui.editor.toast
                                wire:key="documentation-addendum-editor-{{ $courseDayId }}-{{ $editorVersion }}"
                                wireModel="documentationAddendum"
                                height="360px"
                                placeholder="Ergänzung zu diesem Unterrichtstag eingeben…"
                            />
                        </div>
                        @error('documentationAddendum')
                            <div class="px-4 pb-3 text-xs text-rose-600">{{ $message }}</div>
                        @enderror
                    </section>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <label for="documentation-addendum-status" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-600">Status</label>
                            <select
                                id="documentation-addendum-status"
                                wire:model="documentationAddendumStatus"
                                class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-800 focus:border-sky-500 focus:ring-sky-500"
                            >
                                <option value="{{ \App\Models\CourseDay::DOCUMENTATION_ADDENDUM_STATUS_DRAFT }}">Entwurf</option>
                                <option value="{{ \App\Models\CourseDay::DOCUMENTATION_ADDENDUM_STATUS_PUBLISHED }}">Veröffentlicht</option>
                            </select>
                            @error('documentationAddendumStatus')
                                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-600">Systemdaten</div>
                            @if($savedByName || $savedAt)
                                <div class="mt-2 space-y-1">
                                    <div><span class="font-medium text-slate-700">Zuletzt gespeichert von:</span> {{ $savedByName ?? 'Gelöschter Benutzer' }}</div>
                                    <div><span class="font-medium text-slate-700">Gespeichert am:</span> {{ $savedAt ?? '—' }} Uhr</div>
                                </div>
                            @else
                                <p class="mt-2 italic text-slate-400">Noch nicht gespeichert.</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </x-slot>

        <x-slot name="footer">
            <div class="flex w-full justify-end gap-2">
                <x-ui.buttons.button-basic wire:click="close" :size="'sm'" :mode="'secondary'" wire:loading.attr="disabled">
                    Schließen
                </x-ui.buttons.button-basic>
                <x-ui.buttons.button-basic wire:click="save" :size="'sm'" :mode="'primary'" wire:loading.attr="disabled" wire:target="save">
                    <i wire:loading.remove wire:target="save" class="fal fa-save mr-1"></i>
                    <i wire:loading wire:target="save" class="fad fa-spinner-third fa-spin mr-1"></i>
                    Speichern
                </x-ui.buttons.button-basic>
            </div>
        </x-slot>
    </x-dialog-modal>
</div>
