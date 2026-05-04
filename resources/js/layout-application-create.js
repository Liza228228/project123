/**
 * Форма «Новая заявка по макету»: выбор макета, поля с contenteditable и PDF.
 */
export function registerLayoutApplicationCreate(Alpine) {
    Alpine.data('layoutApplicationCreate', (cfg) => ({
        layouts: cfg.layouts || [],
        users: cfg.users || [],
        applications: cfg.applications || [],
        /** Серверные схемы по id макета — без отдельного fetch (надёжно для бухгалтера и без пересборки Vite). */
        layoutSchemasById: cfg.layoutSchemasById && typeof cfg.layoutSchemasById === 'object' ? cfg.layoutSchemasById : {},
        /** Базовый URL без id: …/applications/installation-act/layout-schema */
        schemaJsonBase: cfg.schemaJsonBase || cfg.schemaBase || '',
        storeUrl: cfg.storeUrl,
        token: cfg.token,
        layoutId: null,
        footerPreset: 'one_signer_author',
        signatureSlotsCount: 1,
        signatureRoles: {},
        signatureRoleNames: {},
        /** @type {number[]} */
        selectedApplicationIds: [],
        selectedApplicationEquipment: '',
        insertEquipmentFormat: 'list',
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
            this.$watch('selectedApplicationIds', () => {
                this.selectedApplicationEquipment = '';
            });
        },
        selectAllApplications() {
            this.selectedApplicationIds = (this.applications || []).map((a) => Number(a.id)).filter((id) => id > 0);
        },
        clearApplicationSelection() {
            this.selectedApplicationIds = [];
        },
        get allApplicationsSelected() {
            const apps = this.applications || [];
            const ids = this.selectedApplicationIds || [];
            return apps.length > 0 && ids.length === apps.length;
        },
        toggleSelectAllApplications() {
            if (this.allApplicationsSelected) {
                this.clearApplicationSelection();
            } else {
                this.selectAllApplications();
            }
        },
        resetLayoutSchemaState() {
            this.fields = [];
            this.footerPreset = 'one_signer_author';
            this.signatureSlotsCount = 1;
            this.signatureRoles = {};
            this.signatureRoleNames = {};
        },
        getEmbeddedLayoutSchema() {
            const id = this.layoutId;
            const map = this.layoutSchemasById;
            if (!id || !map || typeof map !== 'object') {
                return null;
            }
            const raw = map[id] ?? map[String(id)];
            return raw && typeof raw === 'object' ? raw : null;
        },
        applyLayoutSchemaData(d) {
            if (!d || typeof d !== 'object') {
                return;
            }
            this.footerPreset = d.pdf_footer_preset || 'one_signer_author';
            const rawSlots = d.signature_slots_count;
            const n = Number(rawSlots);
            this.signatureSlotsCount = Number.isFinite(n) ? Math.max(0, Math.min(3, n)) : 1;
            this.signatureRoles = d.signature_roles && typeof d.signature_roles === 'object' ? d.signature_roles : {};
            this.signatureRoleNames =
                d.signature_role_names && typeof d.signature_role_names === 'object' ? d.signature_role_names : {};
            const raw = Array.isArray(d.fields) ? d.fields : [];
            this.fields = raw.map((f, idx) => ({
                key: f.key,
                label: f.label || f.key,
                type: f.type === 'number' ? 'number' : f.type === 'textarea' ? 'textarea' : 'text',
                slug: `f${idx}_${this.slugify(String(f.key))}`,
            }));
        },
        async loadFields() {
            if (!this.layoutId) {
                this.resetLayoutSchemaState();
                return;
            }
            this.loading = true;
            this.resetLayoutSchemaState();
            try {
                const embedded = this.getEmbeddedLayoutSchema();
                if (embedded) {
                    this.applyLayoutSchemaData(embedded);
                } else {
                    const base = (this.schemaJsonBase || '').replace(/\/$/, '');
                    const r = await fetch(`${base}/${this.layoutId}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!r.ok) {
                        throw new Error(`HTTP ${r.status}`);
                    }
                    const d = await r.json();
                    this.applyLayoutSchemaData(d);
                }
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
                this.resetLayoutSchemaState();
                if (typeof window !== 'undefined' && window.console?.warn) {
                    console.warn('layoutApplicationCreate.loadFields', e);
                }
            }
            this.loading = false;
        },
        get signerSlotCount() {
            const configured = Number(this.signatureSlotsCount);
            if (Number.isFinite(configured) && configured >= 0) {
                return Math.max(0, Math.min(3, configured));
            }
            const p = this.footerPreset;
            return p === 'three_signers' ? 3 : p === 'two_signers' ? 2 : 1;
        },
        signerIndices() {
            const n = this.signerSlotCount;
            return Array.from({ length: n }, (_, i) => i + 1);
        },
        cleanEquipmentPayloadRow(r) {
            if (!r || typeof r !== 'object') {
                return r;
            }
            const { __sourceAppId, __optionLabel, ...rest } = r;

            return rest;
        },
        selectedApplicationEquipmentOptions() {
            const ids = Array.isArray(this.selectedApplicationIds)
                ? this.selectedApplicationIds.map((x) => Number(x)).filter((id) => id > 0)
                : [];
            if (ids.length === 0) {
                return [];
            }
            const multi = ids.length > 1;
            const rows = [];
            for (const id of ids) {
                const app = this.applications.find((a) => Number(a.id || 0) === id);
                if (!app || !Array.isArray(app.equipment)) {
                    continue;
                }
                for (const eq of app.equipment) {
                    const line = String(eq?.line || '').trim();
                    if (line === '') {
                        continue;
                    }
                    rows.push({
                        name: String(eq?.name || ''),
                        quantity: String(eq?.quantity || ''),
                        line,
                        ...(multi ? { __sourceAppId: id, __optionLabel: `#${id} ${line}` } : {}),
                    });
                }
            }

            return rows;
        },
        setActiveEditorField(fieldKey) {
            this.activeEditorFieldKey = String(fieldKey || '');
        },
        normalizeEquipmentRow(row) {
            if (!row || typeof row !== 'object') {
                return null;
            }
            const name = String(row.name || '').trim();
            const quantity = String(row.quantity || '').trim();
            const line =
                String(row.line || '').trim() ||
                (name || quantity ? `${name} x ${quantity}`.trim() : '');
            if (line === '') {
                return null;
            }
            return { name, quantity, line };
        },
        escapeHtmlForPdfCell(s) {
            return String(s ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;');
        },
        buildEquipmentInsertHtml(rawRows) {
            if (!Array.isArray(rawRows) || rawRows.length === 0) {
                return '';
            }
            const hasAppBreak = rawRows.some((r) => r && r.__sourceAppId != null);
            if (!hasAppBreak) {
                const flat = rawRows
                    .map((r) => this.normalizeEquipmentRow(this.cleanEquipmentPayloadRow(r)))
                    .filter(Boolean);
                if (flat.length === 0) {
                    return '';
                }
                if (this.insertEquipmentFormat === 'table') {
                    const bodyRows = flat
                        .map(
                            (r) =>
                                `<tr><td>${this.escapeHtmlForPdfCell(r.name)}</td><td>${this.escapeHtmlForPdfCell(r.quantity)}</td></tr>`
                        )
                        .join('');
                    return (
                        '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;">' +
                        '<thead><tr><th>Наименование</th><th>Количество</th></tr></thead>' +
                        '<tbody>' +
                        bodyRows +
                        '</tbody></table><br>'
                    );
                }
                return flat.map((r) => `- ${this.escapeHtmlForPdfCell(r.line)}`).join('<br>') + '<br>';
            }
            const groups = [];
            let curId = null;
            let bucket = [];
            const flush = () => {
                if (bucket.length === 0) {
                    return;
                }
                groups.push({ appId: curId, rows: bucket.slice() });
                bucket = [];
            };
            for (const r of rawRows) {
                const id = r.__sourceAppId;
                const n = this.normalizeEquipmentRow(this.cleanEquipmentPayloadRow(r));
                if (!n) {
                    continue;
                }
                if (id !== curId) {
                    flush();
                    curId = id;
                }
                bucket.push(n);
            }
            flush();
            if (groups.length === 0) {
                return '';
            }
            const parts = [];
            for (const grp of groups) {
                const header = `<p style="margin:0.4em 0 0.2em 0;"><strong>Заявка №${this.escapeHtmlForPdfCell(String(grp.appId))}</strong></p>`;
                if (this.insertEquipmentFormat === 'table') {
                    const bodyRows = grp.rows
                        .map(
                            (r) =>
                                `<tr><td>${this.escapeHtmlForPdfCell(r.name)}</td><td>${this.escapeHtmlForPdfCell(r.quantity)}</td></tr>`
                        )
                        .join('');
                    parts.push(
                        header +
                            '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;">' +
                            '<thead><tr><th>Наименование</th><th>Количество</th></tr></thead><tbody>' +
                            bodyRows +
                            '</tbody></table>'
                    );
                } else {
                    parts.push(header + grp.rows.map((r) => `- ${this.escapeHtmlForPdfCell(r.line)}`).join('<br>'));
                }
            }
            return parts.join('<br>') + '<br>';
        },
        insertSelectedEquipmentIntoActiveField() {
            const rawEquipment = String(this.selectedApplicationEquipment || '').trim();
            if (!rawEquipment) {
                window.alert('Сначала выберите оборудование из заявки.');
                return;
            }
            let rows = [];
            if (rawEquipment === '__ALL__') {
                if (!Array.isArray(this.selectedApplicationIds) || this.selectedApplicationIds.length === 0) {
                    window.alert('Отметьте одну или несколько заявок (или нажмите «Все заявки»).');
                    return;
                }
                rows = this.selectedApplicationEquipmentOptions();
                if (!Array.isArray(rows) || rows.length === 0) {
                    window.alert('В выбранных заявках нет строк оборудования.');
                    return;
                }
            } else {
                let equipmentRow = null;
                try {
                    equipmentRow = JSON.parse(rawEquipment);
                } catch (e) {
                    equipmentRow = null;
                }
                const one = this.normalizeEquipmentRow(equipmentRow);
                if (!one) {
                    window.alert('Не удалось прочитать выбранную позицию оборудования.');
                    return;
                }
                rows = [one];
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
            const insertionHtml = this.buildEquipmentInsertHtml(rows);
            if (!insertionHtml) {
                window.alert('Не удалось сформировать фрагмент для вставки.');
                return;
            }
            el.focus();
            try {
                document.execCommand('insertHTML', false, insertionHtml);
            } catch (e) {
                el.innerHTML = `${el.innerHTML}${insertionHtml}`;
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
