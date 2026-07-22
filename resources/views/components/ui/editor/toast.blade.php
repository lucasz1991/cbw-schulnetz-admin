@props([
    'wireModel' => null,
    'value' => '',
    'height' => '550px',
    'previewStyle' => 'tab',
    'initialEditType' => 'wysiwyg',
    'disableImages' => true,
    'placeholder' => 'Schreibe hier deine Notizen…',
])

@php
    $safeWireKey = str_replace(['.', ':'], '-', $wireModel ?? 'notes');
    $id = "tui-editor-{$safeWireKey}";
@endphp

@script
<script>
    window.tuiEditorFactory = window.tuiEditorFactory || function (opts) {
        return {
            opts,
            editor: null,
            internalChange: false,

            waitFor(cond, t = 4000, every = 25) {
                return new Promise((resolve) => {
                    const start = Date.now();
                    (function tick() {
                        if (cond()) return resolve();
                        if (Date.now() - start >= t) return resolve();
                        setTimeout(tick, every);
                    })();
                });
            },

            async initOnce() {
                await this.waitFor(() => window.toastui && window.toastui.Editor);

                if (!window.toastui || !window.toastui.Editor) {
                    return;
                }

                this.editor = new toastui.Editor({
                    el: this.$refs.holder,
                    height: this.opts.height,
                    initialEditType: this.opts.initialEditType,
                    previewStyle: this.opts.previewStyle,
                    placeholder: this.opts.placeholder,
                    initialValue: (this.opts.model ?? this.opts.initialValue ?? ''),
                    usageStatistics: false,
                    toolbarItems: [
                        ['heading', 'bold', 'italic', 'strike'],
                        ['hr', 'quote'],
                        ['ul', 'ol', 'task'],
                        ['table', 'link'],
                    ],
                    hooks: {
                        addImageBlobHook: (blob, cb) => {
                            if (this.opts.disableImages) return;
                        },
                    },
                });

                this.editor.on('change', () => {
                    this.internalChange = true;
                    const html = this.editor.getHTML() || '';
                    if (this.opts.model !== html) this.opts.model = html;
                    this.$nextTick(() => (this.internalChange = false));
                });

                this.$watch('opts.model', (nv) => {
                    if (this.internalChange || !this.editor) return;
                    const next = nv || '';
                    const current = this.editor.getHTML() || '';
                    if (next !== current) {
                        const selection = this.editor.getSelection?.();
                        this.editor.setHTML(next);
                        if (selection) this.editor.setSelection(selection[0], selection[1]);
                    }
                });
            },
        };
    };
</script>
@endscript

<div
    x-data="tuiEditorFactory({
        model: @if($wireModel) @entangle($wireModel).live @else null @endif,
        height: @js($height),
        previewStyle: @js($previewStyle),
        initialEditType: @js($initialEditType),
        disableImages: @js($disableImages),
        placeholder: @js($placeholder),
        initialValue: @js($value ?? ''),
    })"
    x-init="initOnce()"
    class="tui-editor-wrapper text-base"
>
    <style>
        .toastui-editor-mode-switch { display: none !important; }
        .tui-editor-wrapper .toastui-editor-contents {
            font-size: 1rem !important;
            line-height: 1.5rem !important;
        }
        .tui-editor-wrapper .toastui-editor-defaultUI {
            border: 0 solid #fff !important;
        }
        .tui-editor-wrapper .toastui-editor-defaultUI-toolbar {
            background-color: transparent !important;
            border-bottom: 1px solid rgb(209, 213, 219) !important;
            padding: 0 .5rem !important;
        }
        @media only screen and (max-width: 480px) {
            .tui-editor-wrapper .toastui-editor-defaultUI .toastui-editor-toolbar {
                position: relative !important;
            }
            .tui-editor-wrapper .toastui-editor-popup {
                margin-left: 10px !important;
            }
            .tui-editor-wrapper .toastui-editor-popup.toastui-editor-popup-add-heading {
                width: auto !important;
                left: unset !important;
            }
        }
    </style>

    <div id="{{ $id }}" x-ref="holder" class="text-base" wire:ignore></div>
</div>
