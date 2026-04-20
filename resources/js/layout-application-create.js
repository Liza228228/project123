/**
 * Форма «Новая заявка по макету»: выбор макета, поля с contenteditable и PDF.
 */
export function registerLayoutApplicationCreate(Alpine) {
    Alpine.data('layoutApplicationCreate', (cfg) => ({
        layouts: cfg.layouts || [],
        users: cfg.users || [],
        schemaBase: cfg.schemaBase,
        storeUrl: cfg.storeUrl,
        token: cfg.token,
        layoutId: null,
        footerPreset: 'one_signer_author',
        fields: [],
        loading: false,
        fontFamily: 'Times New Roman',
        fontSizePt: 11,
        slugify(k) {
            return String(k).replace(/[^a-zA-Z0-9_-]/g, '_').slice(0, 80) || 'f';
        },
        init() {
            try {
                document.execCommand('styleWithCSS', false, true);
            } catch (e) {
                /* ignore */
            }
            if (cfg.preselectLayoutId > 0) {
                this.layoutId = cfg.preselectLayoutId;
                this.$nextTick(() => this.loadFields());
            }
        },
        async loadFields() {
            if (!this.layoutId) {
                this.fields = [];
                this.footerPreset = 'one_signer_author';
                return;
            }
            this.loading = true;
            this.fields = [];
            this.footerPreset = 'one_signer_author';
            try {
                const r = await fetch(`${this.schemaBase}/${this.layoutId}/schema-json`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const d = await r.json();
                this.footerPreset = d.pdf_footer_preset || 'one_signer_author';
                const raw = Array.isArray(d.fields) ? d.fields : [];
                this.fields = raw.map((f, idx) => ({
                    key: f.key,
                    label: f.label || f.key,
                    type: f.type === 'number' ? 'number' : f.type === 'textarea' ? 'textarea' : 'text',
                    slug: `f${idx}_${this.slugify(String(f.key))}`,
                }));
                await this.$nextTick();
                this.fields
                    .filter((f) => f.type !== 'number')
                    .forEach((f) => {
                        const el = document.getElementById(`editor-${f.slug}`);
                        const h = document.getElementById(`hidden-${f.slug}`);
                        if (el && h) {
                            h.value = el.innerHTML;
                        }
                    });
            } catch (e) {
                this.fields = [];
                this.footerPreset = 'one_signer_author';
            }
            this.loading = false;
        },
        get signerSlotCount() {
            const p = this.footerPreset;
            if (p === 'two_signers') {
                return 2;
            }
            if (p === 'three_signers') {
                return 3;
            }
            return 0;
        },
        signerIndices() {
            const n = this.signerSlotCount;
            return Array.from({ length: n }, (_, i) => i + 1);
        },
        syncRich(key, el) {
            const f = this.fields.find((x) => x.key === key);
            if (!f) {
                return;
            }
            const h = document.getElementById(`hidden-${f.slug}`);
            if (h) {
                h.value = el.innerHTML;
            }
        },
        execOn(key, cmd) {
            const f = this.fields.find((x) => x.key === key);
            if (!f) {
                return;
            }
            const el = document.getElementById(`editor-${f.slug}`);
            if (!el) {
                return;
            }
            el.focus();
            try {
                document.execCommand(cmd, false, null);
            } catch (err) {
                /* ignore */
            }
            const h = document.getElementById(`hidden-${f.slug}`);
            if (h) {
                h.value = el.innerHTML;
            }
        },
        applySelectionStyle(key) {
            const f = this.fields.find((x) => x.key === key);
            if (!f) {
                return;
            }
            const el = document.getElementById(`editor-${f.slug}`);
            if (!el) {
                return;
            }
            el.focus();
            const sel = window.getSelection();
            if (!sel || !sel.rangeCount || !sel.toString().trim()) {
                return;
            }
            const range = sel.getRangeAt(0);
            const span = document.createElement('span');
            span.style.fontFamily = this.fontFamily;
            span.style.fontSize = `${this.fontSizePt}pt`;
            try {
                span.appendChild(range.extractContents());
                range.insertNode(span);
            } catch (e) {
                try {
                    const html = `<span style="font-family:${this.fontFamily};font-size:${this.fontSizePt}pt">${sel.toString()}</span>`;
                    document.execCommand('insertHTML', false, html);
                } catch (e2) {
                    /* ignore */
                }
            }
            sel.removeAllRanges();
            const h = document.getElementById(`hidden-${f.slug}`);
            if (h) {
                h.value = el.innerHTML;
            }
        },
        async submit(ev) {
            this.fields
                .filter((f) => f.type !== 'number')
                .forEach((f) => {
                    const el = document.getElementById(`editor-${f.slug}`);
                    const h = document.getElementById(`hidden-${f.slug}`);
                    if (el && h) {
                        h.value = el.innerHTML;
                    }
                });
            const form = ev.target;
            const fd = new FormData(form);
            const res = await fetch(this.storeUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json, application/pdf',
                    'X-CSRF-TOKEN': this.token,
                },
                body: fd,
                credentials: 'same-origin',
            });
            const ct = (res.headers.get('content-type') || '').toLowerCase();
            if (res.status === 422) {
                window.alert('Проверьте заполнение полей.');
                return;
            }
            if (res.ok && ct.includes('application/pdf')) {
                const blob = await res.blob();
                let filename = 'zajavka.pdf';
                const cd = res.headers.get('content-disposition');
                if (cd) {
                    const m = /filename\*?=(?:UTF-8'')?["']?([^";\n]+)/i.exec(cd);
                    if (m && m[1]) {
                        try {
                            filename = decodeURIComponent(m[1].replace(/["']/g, '').trim());
                        } catch (e) {
                            filename = m[1].replace(/["']/g, '').trim();
                        }
                    }
                }
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                a.rel = 'noopener';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
                return;
            }
            window.alert('Не удалось сформировать PDF.');
        },
    }));
}
