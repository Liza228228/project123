// скрипт на странице
import Alpine from 'alpinejs';

document.addEventListener('alpine:init', () => {
    Alpine.data('installationActPhotoGallery', (config) => ({
        open: false,
        lightSrc: '',
        lightAlt: '',
        lightIndex: 0,
        urls: Array.isArray(config.urls) ? config.urls : [],

        openAt(i) {
            const idx = Number(i);
            if (idx < 0 || idx >= this.urls.length) {
                return;
            }
            this.lightIndex = idx;
            this.lightSrc = this.urls[idx];
            this.lightAlt = 'Фото '.concat(String(idx + 1), ' из ').concat(String(this.urls.length));
            this.open = true;
            document.body.classList.add('overflow-hidden');
        },

        close() {
            this.open = false;
            this.lightSrc = '';
            document.body.classList.remove('overflow-hidden');
        },

        prev() {
            if (this.lightIndex > 0) {
                this.openAt(this.lightIndex - 1);
            }
        },

        next() {
            if (this.lightIndex < this.urls.length - 1) {
                this.openAt(this.lightIndex + 1);
            }
        },
    }));
});
