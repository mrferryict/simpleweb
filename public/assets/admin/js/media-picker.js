/**
 * Alpine Media Picker (Phase 4 / Task 4.6).
 *
 * Selection writes media_id into a hidden input; form POST persists via existing pipeline.
 * List HTML is fetched via GET (no mutation / no CSRF change).
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('mediaPicker', (config) => ({
        mediaId: Number(config.mediaId) || 0,
        label: String(config.label || ''),
        mime: String(config.mime || ''),
        size: String(config.size || ''),
        status: String(config.status || ''),
        previewUrl: String(config.previewUrl || ''),
        downloadUrl: String(config.downloadUrl || ''),
        type: String(config.type || 'IMAGE'),
        listUrl: String(config.listUrl || ''),
        open: false,
        loading: false,
        loadError: '',
        listHtml: '',

        clear() {
            this.mediaId = 0;
            this.label = '';
            this.mime = '';
            this.size = '';
            this.status = '';
            this.previewUrl = '';
            this.downloadUrl = '';
            this.open = false;
        },

        select(id, label, mime, size, preview, download) {
            this.mediaId = Number(id) || 0;
            this.label = String(label || '');
            this.mime = String(mime || '');
            this.size = String(size || '');
            this.status = 'ACTIVE';
            this.previewUrl = String(preview || '');
            this.downloadUrl = String(download || '');
            this.open = false;
        },

        async toggleList() {
            this.open = !this.open;
            if (this.open && this.listHtml === '' && !this.loading) {
                await this.loadList();
            }
        },

        async loadList() {
            if (!this.listUrl) {
                this.loadError = 'Picker URL is missing.';
                return;
            }
            this.loading = true;
            this.loadError = '';
            try {
                const response = await fetch(this.listUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) {
                    this.loadError = 'Unable to load media list.';
                    this.listHtml = '';
                    return;
                }
                this.listHtml = await response.text();
            } catch (e) {
                this.loadError = 'Unable to load media list.';
                this.listHtml = '';
            } finally {
                this.loading = false;
            }
        },

        onPickClick(event) {
            const btn = event.target.closest('[data-pick-media]');
            if (!btn || !this.$el.contains(btn)) {
                return;
            }
            this.select(
                btn.getAttribute('data-media-id'),
                btn.getAttribute('data-label'),
                btn.getAttribute('data-mime'),
                btn.getAttribute('data-size'),
                btn.getAttribute('data-preview'),
                btn.getAttribute('data-download'),
            );
        },
    }));
});
