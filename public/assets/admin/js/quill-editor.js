/**
 * Alpine.js `quillEditor` bridge for Control Panel RICH_TEXT (ADR-014 / Phase 3 Task 3.5).
 *
 * Quill is UX only. Server-side RichTextSanitizer remains the security boundary.
 * Initial HTML is read from the backing textarea in the DOM (never from inline JS).
 */
(function () {
    'use strict';

    /** Toolbar limited to ADR-014 allowlist-aligned controls. */
    var TOOLBAR = [
        [{ header: [1, 2, 3, 4, false] }],
        ['bold', 'italic', 'underline'],
        ['link'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['blockquote'],
    ];

    function registerQuillEditor() {
        if (typeof Alpine === 'undefined') {
            return;
        }

        Alpine.data('quillEditor', function () {
            return {
                quill: null,
                editorReady: false,
                _onSubmit: null,

                init: function () {
                    var self = this;
                    var backing = this.$refs.backingField;
                    var container = this.$refs.editorContainer;

                    if (!backing || !container) {
                        return;
                    }

                    if (typeof Quill === 'undefined') {
                        return;
                    }

                    if (this.quill) {
                        return;
                    }

                    try {
                        this.quill = new Quill(container, {
                            theme: 'snow',
                            modules: {
                                toolbar: TOOLBAR,
                            },
                        });

                        var initial = backing.value || '';
                        if (initial !== '') {
                            var delta = this.quill.clipboard.convert({ html: initial });
                            this.quill.setContents(delta, 'silent');
                        }

                        this.quill.on('text-change', function () {
                            self.syncToBacking();
                        });

                        this.syncToBacking();
                        this.editorReady = true;
                        backing.classList.add('smite-quill-fallback--active-hidden');

                        var form = this.$el.closest('form');
                        if (form) {
                            this._onSubmit = function () {
                                self.syncToBacking();
                            };
                            form.addEventListener('submit', this._onSubmit);
                        }
                    } catch (err) {
                        this.quill = null;
                        this.editorReady = false;
                        backing.classList.remove('smite-quill-fallback--active-hidden');
                    }
                },

                syncToBacking: function () {
                    if (!this.quill || !this.$refs.backingField) {
                        return;
                    }
                    this.$refs.backingField.value = this.quill.root.innerHTML;
                },

                destroy: function () {
                    if (this._onSubmit) {
                        var form = this.$el.closest('form');
                        if (form) {
                            form.removeEventListener('submit', this._onSubmit);
                        }
                        this._onSubmit = null;
                    }
                    this.quill = null;
                    this.editorReady = false;
                },
            };
        });
    }

    document.addEventListener('alpine:init', registerQuillEditor);
})();
