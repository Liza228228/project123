/**
 * Форма «Новая заявка по макету»: выбор макета, поля с contenteditable и PDF.
 */
export function registerLayoutApplicationCreate(Alpine) {
    Alpine.data('requestLayoutTableFill', (config) => ({
        columns: Array.isArray(config?.columns) ? config.columns : ['Столбец 1'],
        savedRows: Array.isArray(config?.savedRows) ? config.savedRows : [],
        rowCount: Math.max(1, Math.min(30, Number(config?.initialRowCount) || 1)),
        maxRows: 30,
        minRows: 1,
        clampRowCount() {
            const n = Number(this.rowCount);
            this.rowCount = Math.max(this.minRows, Math.min(this.maxRows, Number.isFinite(n) ? n : this.minRows));
        },
        rowIndices() {
            this.clampRowCount();

            return Array.from({ length: this.rowCount }, (_, i) => i);
        },
        colIndices() {
            return this.columns.map((_, i) => i);
        },
        cellValue(rowIdx, colIdx) {
            const row = this.savedRows[rowIdx];

            return row && row[colIdx] !== undefined && row[colIdx] !== null ? String(row[colIdx]) : '';
        },
    }));

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
        /** Для селектов подписантов (значения — строки id или ''). */
        signerSelections: { 1: '', 2: '', 3: '' },
        layoutViewerContext: (() => {
            const c = cfg.layoutViewerContext && typeof cfg.layoutViewerContext === 'object' ? cfg.layoutViewerContext : {};
            return {
                isBoilerChief: Boolean(c.isBoilerChief),
                foremanRoleId: Number(c.foremanRoleId) || 4,
                chiefSubdivisionIds: Array.isArray(c.chiefSubdivisionIds)
                    ? c.chiefSubdivisionIds.map((id) => Number(id)).filter((id) => id > 0)
                    : [],
            };
        })(),
        layoutLocked: Boolean(cfg.layoutLocked),
        initialSubmissionPayload:
            cfg.initialSubmissionPayload && typeof cfg.initialSubmissionPayload === 'object'
                ? cfg.initialSubmissionPayload
                : {},
        submissionHydrated: false,
        applicationSearch: '',
        selectedApplicationEquipment: '',
        insertEquipmentFormat: 'list',
        activeEditorFieldKey: '',
        fields: [],
        /** Число строк таблицы при заполнении отчёта (ключ поля → 1…30). */
        tableFillRowCounts: {},
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
                this.$nextTick(() => this.applyDefaultForemanForSelectedApplications());
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
            this.tableFillRowCounts = {};
            this.footerPreset = 'one_signer_author';
            this.signatureSlotsCount = 1;
            this.signatureRoles = {};
            this.signatureRoleNames = {};
            this.signerSelections = { 1: '', 2: '', 3: '' };
        },
        resetTableFillRowCounts() {
            const counts = {};
            for (const f of this.fields) {
                if (f.type === 'table' && f.key) {
                    counts[String(f.key)] = 1;
                }
            }
            this.tableFillRowCounts = counts;
        },
        getTableRowCount(fieldKey) {
            const n = Number(this.tableFillRowCounts[fieldKey]);
            if (!Number.isFinite(n) || n < 1) {
                return 1;
            }

            return Math.min(30, Math.floor(n));
        },
        setTableRowCount(fieldKey, value) {
            const n = Math.max(1, Math.min(30, Number(value) || 1));
            this.tableFillRowCounts = { ...this.tableFillRowCounts, [String(fieldKey)]: n };
        },
        tableRowIndicesForField(field) {
            const key = field?.key != null ? String(field.key) : '';

            return Array.from({ length: this.getTableRowCount(key) }, (_, i) => i);
        },
        tableColumnIndices(field) {
            const cols = field && Array.isArray(field.table_columns) ? field.table_columns : [];

            return cols.map((_, i) => i);
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
            const allowedTypes = new Set(['text', 'number', 'textarea', 'date', 'table']);
            this.fields = raw.map((f, idx) => {
                const t = String(f.type || 'text');
                const type = allowedTypes.has(t) ? t : 'text';
                const base = {
                    key: f.key,
                    label: f.label || f.key,
                    type,
                    slug: `f${idx}_${this.slugify(String(f.key))}`,
                };
                if (type === 'table') {
                    const cols = Array.isArray(f.table_columns) ? f.table_columns.map((c) => String(c || '')) : ['Столбец 1'];
                    const filtered = cols.map((c) => c.trim()).filter((c) => c !== '');
                    base.table_columns = filtered.length ? filtered : ['Столбец 1'];
                }

                return base;
            });
            this.resetTableFillRowCounts();
        },
        async loadFields() {
            if (!this.layoutId) {
                this.resetLayoutSchemaState();
                this.submissionHydrated = false;
                return;
            }
            this.loading = true;
            this.submissionHydrated = false;
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
                    .filter((f) => f.type === 'text' || f.type === 'textarea')
                    .forEach((f) => {
                        const el = document.getElementById(`editor-${f.slug}`);
                        const h = document.getElementById(`hidden-${f.slug}`);
                        if (el && h) {
                            h.value = el.innerHTML;
                        }
                    });
                this.applyDefaultForemanForSelectedApplications();
                this.hydrateSubmissionIfNeeded();
            } catch (e) {
                this.resetLayoutSchemaState();
                this.submissionHydrated = false;
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
            if (this.insertEquipmentFormat === 'table') {
                const bodyChunks = [];
                for (const grp of groups) {
                    bodyChunks.push(
                        `<tr><td colspan="2" style="background-color:#f3f4f6;font-weight:700;padding:6px 8px;border:1px solid #111;text-align:left;">Заявка №${this.escapeHtmlForPdfCell(String(grp.appId))}</td></tr>`
                    );
                    for (const r of grp.rows) {
                        bodyChunks.push(
                            `<tr><td>${this.escapeHtmlForPdfCell(r.name)}</td><td>${this.escapeHtmlForPdfCell(r.quantity)}</td></tr>`
                        );
                    }
                }
                return (
                    '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;">' +
                    '<thead><tr><th>Наименование</th><th>Количество</th></tr></thead><tbody>' +
                    bodyChunks.join('') +
                    '</tbody></table><br>'
                );
            }
            const parts = [];
            for (const grp of groups) {
                const header = `<p style="margin:0.4em 0 0.2em 0;"><strong>Заявка №${this.escapeHtmlForPdfCell(String(grp.appId))}</strong></p>`;
                parts.push(header + grp.rows.map((r) => `- ${this.escapeHtmlForPdfCell(r.line)}`).join('<br>'));
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
            if (!f || (f.type !== 'text' && f.type !== 'textarea')) {
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
            return roleName ? `Подпись ${slot} (сотрудник: ${roleName})` : `Подпись ${slot}`;
        },
        usersForSignerSlot(slot) {
            const roleId = this.signerRoleId(slot);
            let list = !roleId ? this.users : this.users.filter((u) => Number(u.role_id || 0) === roleId);
            const ctx = this.layoutViewerContext;
            const foremanId = ctx.foremanRoleId || 4;
            if (ctx.isBoilerChief && roleId === foremanId) {
                const chiefSet = new Set(ctx.chiefSubdivisionIds || []);
                if (chiefSet.size === 0) {
                    list = [];
                } else {
                    list = list.filter((u) => {
                        const subs = Array.isArray(u.subdivision_ids) ? u.subdivision_ids : [];
                        return subs.some((sid) => chiefSet.has(Number(sid)));
                    });
                }
            }
            return list;
        },
        applyDefaultForemanForSelectedApplications() {
            const ctx = this.layoutViewerContext;
            const foremanId = ctx.foremanRoleId || 4;
            const ids = Array.isArray(this.selectedApplicationIds)
                ? this.selectedApplicationIds.map((x) => Number(x)).filter((id) => id > 0)
                : [];
            if (ids.length !== 1 || !ctx.isBoilerChief) {
                return;
            }
            const appId = ids[0];
            const app = (this.applications || []).find((a) => Number(a.id || 0) === appId);
            const foremanUserId = app ? Number(app.foreman_user_id || 0) : 0;
            if (foremanUserId <= 0) {
                return;
            }
            for (const n of this.signerIndices()) {
                if (this.signerRoleId(n) !== foremanId) {
                    continue;
                }
                const cur = String(this.signerSelections[n] ?? '').trim();
                if (cur !== '') {
                    continue;
                }
                const allowed = this.usersForSignerSlot(n).some((u) => Number(u.id) === foremanUserId);
                if (allowed) {
                    this.signerSelections[n] = String(foremanUserId);
                }
            }
        },
        hydrateSubmissionIfNeeded() {
            if (!this.layoutLocked || this.submissionHydrated) {
                return;
            }
            const data = this.initialSubmissionPayload;
            if (!data || typeof data !== 'object') {
                this.submissionHydrated = true;
                return;
            }
            for (let i = 1; i <= 3; i += 1) {
                const k = `signer_${i}_user_id`;
                const uid = Number(data[k] ?? 0);
                if (uid > 0) {
                    this.signerSelections[i] = String(uid);
                }
            }
            for (const f of this.fields) {
                const key = f.key != null ? String(f.key) : '';
                if (key === '' || !Object.prototype.hasOwnProperty.call(data, key)) {
                    continue;
                }
                const raw = data[key];
                if (f.type === 'table') {
                    let rows = [];
                    if (typeof raw === 'string' && raw !== '') {
                        try {
                            const parsed = JSON.parse(raw);
                            if (Array.isArray(parsed)) {
                                rows = parsed;
                            }
                        } catch (_e) {
                            rows = [];
                        }
                    } else if (Array.isArray(raw)) {
                        rows = raw;
                    }
                    const colCount = Array.isArray(f.table_columns) ? f.table_columns.length : 0;
                    const rowCount = Math.max(1, Math.min(30, rows.length || 1));
                    this.tableFillRowCounts = { ...this.tableFillRowCounts, [key]: rowCount };
                    const root = this.$root && this.$root.querySelectorAll ? this.$root : document;
                    for (let r = 0; r < rowCount; r += 1) {
                        for (let c = 0; c < colCount; c += 1) {
                            const cell = rows[r]?.[c] ?? rows[r]?.[String(c)] ?? '';
                            const sel = `input[name="values[${key}][${r}][${c}]"]`;
                            const node = root.querySelector(sel);
                            if (node && 'value' in node) {
                                node.value = cell !== null && cell !== undefined ? String(cell) : '';
                            }
                        }
                    }
                    continue;
                }
                const val = raw !== null && raw !== undefined && typeof raw !== 'object' ? String(raw) : '';
                if (f.type === 'text' || f.type === 'textarea') {
                    const el = document.getElementById(`editor-${f.slug}`);
                    const h = document.getElementById(`hidden-${f.slug}`);
                    if (el) {
                        el.innerHTML = val;
                    }
                    if (h) {
                        h.value = val;
                    }
                } else {
                    const nameAttr = `values[${key}]`;
                    const root = this.$root && this.$root.querySelectorAll ? this.$root : document;
                    const nodes = root.querySelectorAll('input, textarea');
                    for (const node of nodes) {
                        if (node.getAttribute('name') === nameAttr && 'value' in node) {
                            node.value = val;
                            break;
                        }
                    }
                }
            }
            this.submissionHydrated = true;
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
                .filter((f) => f.type === 'text' || f.type === 'textarea')
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
