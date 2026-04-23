/**
 * Форма «Новая заявка по макету»: выбор макета, поля с contenteditable и PDF.
 */
export function registerLayoutApplicationCreate(Alpine) {
    Alpine.data('layoutApplicationCreate', (cfg) => ({
        layouts: cfg.layouts || [],
        users: cfg.users || [],
        applications: cfg.applications || [],
        schemaBase: cfg.schemaBase,
        storeUrl: cfg.storeUrl,
        token: cfg.token,
        layoutId: null,
        footerPreset: 'one_signer_author',
        signatureSlotsCount: 1,
        signatureRoles: {},
        signatureRoleNames: {},
        selectedApplicationId: '',
        selectedApplicationEquipment: '',
        activeEditorFieldKey: '',
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
                this.signatureSlotsCount = 1;
                this.signatureRoles = {};
                this.signatureRoleNames = {};
                return;
            }
            this.loading = true;
            this.fields = [];
            this.footerPreset = 'one_signer_author';
            this.signatureSlotsCount = 1;
            this.signatureRoles = {};
            this.signatureRoleNames = {};
            try {
                const r = await fetch(`${this.schemaBase}/${this.layoutId}/schema-json`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const d = await r.json();
                this.footerPreset = d.pdf_footer_preset || 'one_signer_author';
                this.signatureSlotsCount = Number(d.signature_slots_count || 1);
                this.signatureRoles = d.signature_roles && typeof d.signature_roles === 'object' ? d.signature_roles : {};
                this.signatureRoleNames = d.signature_role_names && typeof d.signature_role_names === 'object' ? d.signature_role_names : {};
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
                this.signatureSlotsCount = 1;
                this.signatureRoles = {};
                this.signatureRoleNames = {};
            }
            this.loading = false;
        },
        get signerSlotCount() {
            const configured = Number(this.signatureSlotsCount || 0);
            if (configured >= 1) {
                return Math.max(1, Math.min(3, configured));
            }
            const p = this.footerPreset;
            return p === 'three_signers' ? 3 : p === 'two_signers' ? 2 : 1;
        },
        signerIndices() {
            const n = this.signerSlotCount;
            return Array.from({ length: n }, (_, i) => i + 1);
        },
        selectedApplicationEquipmentOptions() {
            const appId = Number(this.selectedApplicationId || 0);
            if (!appId) {
                return [];
            }
            const app = this.applications.find((a) => Number(a.id || 0) === appId);
            if (!app || !Array.isArray(app.equipment)) {
                return [];
            }
            return app.equipment;
        },
        setActiveEditorField(fieldKey) {
            this.activeEditorFieldKey = String(fieldKey || '');
        },
        insertSelectedEquipmentIntoActiveField() {
            const equipmentLine = String(this.selectedApplicationEquipment || '').trim();
            if (!equipmentLine) {
                window.alert('Сначала выберите оборудование из заявки.');
                return;
            }
            const key = String(this.activeEditorFieldKey || '').trim();
            if (!key) {
                window.alert('Сначала кликните в текстовое поле, куда вставить оборудование.');
                return;
            }
            const f = this.fields.find((x) => x.key === key);
            if (!f || f.type === 'number') {
                window.alert('Выберите текстовое поле для вставки.');
                return;
            }
            const el = document.getElementById(`editor-${f.slug}`);
            const h = document.getElementById(`hidden-${f.slug}`);
            if (!el || !h) {
                return;
            }
            const escaped = equipmentLine
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;');
            el.focus();
            try {
                document.execCommand('insertHTML', false, `${escaped}<br>`);
            } catch (e) {
                el.innerHTML = `${el.innerHTML}${escaped}<br>`;
            }
            h.value = el.innerHTML;
        },
        signerRoleId(slot) {
            const raw = this.signatureRoles?.[slot] ?? this.signatureRoles?.[String(slot)] ?? 0;
            return Number(raw || 0);
        },
        signerRoleLabel(slot) {
            const roleId = this.signerRoleId(slot);
            if (!roleId) {
                return `Подпись ${slot}`;
            }
            const roleName = this.signatureRoleNames?.[slot] || this.signatureRoleNames?.[String(slot)] || '';
            return roleName ? `Подпись ${slot} (роль: ${roleName})` : `Подпись ${slot}`;
        },
        usersForSignerSlot(slot) {
            const roleId = this.signerRoleId(slot);
            if (!roleId) {
                return this.users;
            }
            return this.users.filter((u) => Number(u.role_id || 0) === roleId);
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
