function normalizeMeasurementMeta(raw) {
    const m = raw && typeof raw === 'object' ? raw : {};
    const typeOptions =
        m.typeOptions && typeof m.typeOptions === 'object' ? m.typeOptions : { piece: 'Штучные' };
    const unitsByType =
        m.unitsByType && typeof m.unitsByType === 'object' ? m.unitsByType : { piece: ['шт'] };
    const unitToType = m.unitToType && typeof m.unitToType === 'object' ? m.unitToType : { шт: 'piece' };
    const defaultType = String(m.defaultType || 'piece');
    const defaultUnit = String(m.defaultUnit || unitsByType[defaultType]?.[0] || 'шт');

    return { typeOptions, unitsByType, unitToType, defaultType, defaultUnit };
}

/**
 * Форма «Новая заявка по макету»: выбор макета, поля с contenteditable и PDF.
 */
export function registerLayoutApplicationCreate(Alpine) {
    Alpine.data('requestLayoutCommercialEstimateFill', (config) => ({
        columns: Array.isArray(config?.columns) ? config.columns : [],
        savedRows: Array.isArray(config?.savedRows) ? config.savedRows : [],
        measurementMeta: normalizeMeasurementMeta(config?.measurementMeta),
        rowUnitTypes: {},
        rowCount: Math.max(1, Math.min(30, Number(config?.initialRowCount) || 1)),
        fieldKey: String(config?.fieldKey || ''),
        maxRows: 30,
        minRows: 1,
        colRoles: { name: 0, unit: 1, qty: 2, price: 3, sum: 4 },
        rowUnitTypeKey(rowIdx) {
            return `${this.fieldKey}:${rowIdx}`;
        },
        measurementTypeOptionEntries() {
            return Object.entries(this.measurementMeta.typeOptions || {}).map(([code, label]) => ({
                code,
                label,
            }));
        },
        unitsForMeasurementType(type) {
            const list = this.measurementMeta.unitsByType?.[type];
            return Array.isArray(list) && list.length ? list : [this.measurementMeta.defaultUnit || 'шт'];
        },
        resolveMeasurementTypeForUnit(unitCode) {
            const code = String(unitCode || '').trim();
            if (!code) {
                return this.measurementMeta.defaultType || 'piece';
            }
            return this.measurementMeta.unitToType?.[code] || this.measurementMeta.defaultType || 'piece';
        },
        rowMeasurementType(rowIdx) {
            const k = this.rowUnitTypeKey(rowIdx);
            if (!this.rowUnitTypes[k]) {
                const savedUnit = this.cellValue(rowIdx, this.colRoles.unit);
                this.rowUnitTypes[k] = this.resolveMeasurementTypeForUnit(savedUnit);
            }
            return this.rowUnitTypes[k];
        },
        onUnitTypeChange(rowIdx, type) {
            const k = this.rowUnitTypeKey(rowIdx);
            this.rowUnitTypes[k] = type;
            const units = this.unitsForMeasurementType(type);
            this.setCellValue(rowIdx, this.colRoles.unit, units[0] || '');
        },
        init() {
            this.$nextTick(() => {
                for (let r = 0; r < this.rowCount; r += 1) {
                    for (let c = 0; c < this.columns.length; c += 1) {
                        if (c === this.colRoles.unit) {
                            const unit = this.cellValue(r, c);
                            const type = this.resolveMeasurementTypeForUnit(unit);
                            this.rowUnitTypes[this.rowUnitTypeKey(r)] = type;
                            if (!unit) {
                                this.setCellValue(r, c, this.unitsForMeasurementType(type)[0] || '');
                            }
                            continue;
                        }
                        const el = this.getCellInput(r, c);
                        if (el) {
                            el.value = this.cellValue(r, c);
                        }
                    }
                }
                this.recalculateAllRows();
                this.notifyTotalsChanged();
            });
        },
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
        colRole(colIdx) {
            const i = Number(colIdx);
            if (i === this.colRoles.unit) return 'unit';
            if (i === this.colRoles.qty) return 'qty';
            if (i === this.colRoles.price) return 'price';
            if (i === this.colRoles.sum) return 'sum';
            return 'name';
        },
        inputName(rowIdx, colIdx) {
            return `values[${this.fieldKey}][${rowIdx}][${colIdx}]`;
        },
        cellValue(rowIdx, colIdx) {
            const row = this.savedRows[rowIdx];
            return row && row[colIdx] !== undefined && row[colIdx] !== null ? String(row[colIdx]) : '';
        },
        parseNumber(raw) {
            const s = String(raw ?? '')
                .replace(/\s+/g, '')
                .replace(',', '.')
                .trim();
            if (s === '') return 0;
            const n = Number(s);
            return Number.isFinite(n) && n >= 0 ? n : 0;
        },
        formatAmount(n) {
            const v = Math.round(Number(n) * 100) / 100;
            if (!Number.isFinite(v)) return '0';
            return String(v)
                .replace(/(\.\d*?[1-9])0+$/u, '$1')
                .replace(/\.0+$/u, '');
        },
        getCellInput(rowIdx, colIdx) {
            return this.$root.querySelector(`[name="${this.inputName(rowIdx, colIdx)}"]`);
        },
        getCellValueFromDom(rowIdx, colIdx) {
            const el = this.getCellInput(rowIdx, colIdx);
            return el ? String(el.value ?? '') : '';
        },
        setCellValue(rowIdx, colIdx, value) {
            const el = this.getCellInput(rowIdx, colIdx);
            if (el) el.value = value == null ? '' : String(value);
        },
        sanitizeNumericInput(event) {
            const el = event.target;
            if (!el) return;
            let v = String(el.value ?? '').replace(/[^\d.,]/g, '');
            const parts = v.replace(/,/g, '.').split('.');
            if (parts.length > 2) {
                v = `${parts.shift()}.${parts.join('')}`;
            } else {
                v = parts.join('.');
            }
            el.value = v;
        },
        onNumericInput(rowIdx, colIdx, event) {
            this.sanitizeNumericInput(event);
            this.recalculateRow(rowIdx);
            this.notifyTotalsChanged();
        },
        rowSum(rowIdx) {
            const qty = this.parseNumber(this.getCellValueFromDom(rowIdx, this.colRoles.qty));
            const price = this.parseNumber(this.getCellValueFromDom(rowIdx, this.colRoles.price));
            return qty * price;
        },
        recalculateRow(rowIdx) {
            this.setCellValue(rowIdx, this.colRoles.sum, this.formatAmount(this.rowSum(rowIdx)));
        },
        recalculateAllRows() {
            for (let r = 0; r < this.rowCount; r += 1) {
                this.recalculateRow(r);
            }
        },
        grandTotal() {
            let total = 0;
            for (let r = 0; r < this.rowCount; r += 1) {
                total += this.rowSum(r);
            }
            return total;
        },
        notifyTotalsChanged() {
            const formatted = this.formatAmount(this.grandTotal());
            window.dispatchEvent(
                new CustomEvent('commercial-estimate-totals-changed', {
                    detail: { total: formatted },
                })
            );
        },
        onRowCountChange() {
            this.$nextTick(() => {
                for (let r = 0; r < this.rowCount; r += 1) {
                    const k = this.rowUnitTypeKey(r);
                    if (!this.rowUnitTypes[k]) {
                        const type = this.measurementMeta.defaultType || 'piece';
                        this.rowUnitTypes[k] = type;
                        if (!this.getCellValueFromDom(r, this.colRoles.unit)) {
                            this.setCellValue(r, this.colRoles.unit, this.unitsForMeasurementType(type)[0] || '');
                        }
                    }
                }
                this.recalculateAllRows();
                this.notifyTotalsChanged();
            });
        },
    }));

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
        submitRedirectsOnSuccess: Boolean(cfg.submitRedirectsOnSuccess),
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
        allowApplicationEquipmentInsert: true,
        layoutCategory: '',
        measurementMeta: normalizeMeasurementMeta(cfg.measurementMeta),
        subdivisionWarehouseOptions: Array.isArray(cfg.subdivisionWarehouseOptions)
            ? cfg.subdivisionWarehouseOptions
            : [],
        subdivisionWarehouseRef: '',
        commercialEstimateUnitTypes: {},
        commercialEstimateGrandTotalFormatted: '0',
        fields: [],
        /** Число строк таблицы при заполнении отчёта (ключ поля → 1…30). */
        tableFillRowCounts: {},
        loading: false,
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
            window.addEventListener('commercial-estimate-totals-changed', (e) => {
                const total = e?.detail?.total != null ? String(e.detail.total) : '0';
                this.commercialEstimateGrandTotalFormatted = total;
                this.syncCommercialEstimateTotalFields(total);
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
            this.allowApplicationEquipmentInsert = true;
            this.layoutCategory = '';
            this.commercialEstimateGrandTotalFormatted = '0';
            this.subdivisionWarehouseRef = '';
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
            this.allowApplicationEquipmentInsert = d.allow_application_equipment_insert !== false;
            this.layoutCategory = String(d.category || '');
            const raw = Array.isArray(d.fields) ? d.fields : [];
            const allowedTypes = new Set(['text', 'number', 'textarea', 'date', 'table', 'subdivision_warehouse']);
            this.fields = raw.map((f, idx) => {
                const t = String(f.type || 'text');
                const type = allowedTypes.has(t) ? t : 'text';
                const base = {
                    key: f.key,
                    label: f.label || f.key,
                    type,
                    slug: `f${idx}_${this.slugify(String(f.key))}`,
                    readonly: Boolean(f.readonly),
                    table_mode: String(f.table_mode || ''),
                };
                if (type === 'table') {
                    const cols = Array.isArray(f.table_columns) ? f.table_columns.map((c) => String(c || '')) : ['Столбец 1'];
                    const filtered = cols.map((c) => c.trim()).filter((c) => c !== '');
                    base.table_columns = filtered.length ? filtered : ['Столбец 1'];
                }

                return base;
            });
            this.resetTableFillRowCounts();
            this.$nextTick(() => {
                if (this.layoutCategory === 'commercial-proposal') {
                    const tableField = this.fields.find((f) => this.isCommercialEstimateField(f));
                    if (tableField?.key) {
                        this.initCommercialEstimateUnitTypesForField(String(tableField.key));
                    }
                    this.recalculateCommercialEstimateAll();
                }
                this.applyDefaultSignerSelection();
                this.restoreSubdivisionWarehouseRefFromPayload();
            });
        },
        subdivisionWarehouseSubdivisionOptions() {
            return (this.subdivisionWarehouseOptions || []).filter((opt) => opt?.kind === 'subdivision');
        },
        subdivisionWarehouseWarehouseOptions() {
            return (this.subdivisionWarehouseOptions || []).filter((opt) => opt?.kind === 'warehouse');
        },
        findSubdivisionWarehouseOption(ref) {
            const key = String(ref || '').trim();
            if (key === '') {
                return null;
            }

            return (this.subdivisionWarehouseOptions || []).find((opt) => String(opt?.value || '') === key) || null;
        },
        escapeHtmlPlain(text) {
            return String(text || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        },
        setRichFieldPlainText(fieldKey, text) {
            const key = String(fieldKey || '');
            const f = this.fields.find((x) => String(x.key) === key);
            if (!f) {
                return;
            }
            const plain = String(text || '').trim();
            const html = plain === '' ? '' : `<div>${this.escapeHtmlPlain(plain)}</div>`;
            const el = document.getElementById(`editor-${f.slug}`);
            const h = document.getElementById(`hidden-${f.slug}`);
            if (el) {
                el.innerHTML = html;
            }
            if (h) {
                h.value = html;
            }
        },
        applySubdivisionWarehouseSelection() {
            const opt = this.findSubdivisionWarehouseOption(this.subdivisionWarehouseRef);
            if (!opt) {
                this.setRichFieldPlainText('подразделение', '');
                this.setRichFieldPlainText('адрес', '');

                return;
            }
            const line =
                String(opt.pdf_line || '').trim() ||
                [String(opt.subdivision_name || '').trim(), String(opt.display_name || '').trim()]
                    .filter(Boolean)
                    .join(', ');
            this.setRichFieldPlainText('подразделение', line);
            this.setRichFieldPlainText('адрес', opt.address || '');
        },
        restoreSubdivisionWarehouseRefFromPayload() {
            if (!this.layoutLocked || !this.initialSubmissionPayload) {
                return;
            }
            const ref = String(this.initialSubmissionPayload._подразделение_ref || '').trim();
            if (ref !== '') {
                this.subdivisionWarehouseRef = ref;
            }
        },
        applyDefaultSignerSelection() {
            if (this.signerSlotCount <= 0) {
                return;
            }
            for (const n of this.signerIndices()) {
                if (String(this.signerSelections[n] ?? '').trim() !== '') {
                    continue;
                }
                const list = this.usersForSignerSlot(n);
                if (list.length > 0) {
                    this.signerSelections[n] = String(list[0].id);
                }
            }
        },
        prepareLayoutFormBeforeSubmit() {
            if (this.layoutCategory === 'commercial-proposal') {
                this.recalculateCommercialEstimateAll();
            }
            this.fields
                .filter(
                    (f) =>
                        (f.type === 'text' || f.type === 'textarea' || f.type === 'subdivision_warehouse') &&
                        !f.readonly
                )
                .forEach((f) => {
                    const el = document.getElementById(`editor-${f.slug}`);
                    const h = document.getElementById(`hidden-${f.slug}`);
                    if (el && h) {
                        h.value = el.innerHTML;
                    }
                });
            for (const key of ['итого_оборудование', 'итого_вся_смета']) {
                const root = this.$root && typeof this.$root.querySelector === 'function' ? this.$root : document;
                const el = root.querySelector(`[name="values[${key}]"]`);
                if (el) {
                    el.value = this.commercialEstimateGrandTotalFormatted;
                }
            }
            this.applyDefaultSignerSelection();
        },
        formatValidationErrors(data) {
            if (!data || typeof data !== 'object') {
                return 'Проверьте заполнение полей.';
            }
            if (data.errors && typeof data.errors === 'object') {
                const lines = Object.values(data.errors)
                    .flat()
                    .map((line) => String(line || '').trim())
                    .filter((line) => line !== '');
                if (lines.length > 0) {
                    return lines.join('\n');
                }
            }
            if (typeof data.message === 'string' && data.message.trim() !== '') {
                return data.message.trim();
            }

            return 'Проверьте заполнение полей.';
        },
        isCommercialEstimateField(field) {
            if (!field || field.type !== 'table') {
                return false;
            }
            if (String(field.table_mode || '') === 'commercial_estimate') {
                return true;
            }
            return this.layoutCategory === 'commercial-proposal';
        },
        commercialEstimateColIndices() {
            return { name: 0, unit: 1, qty: 2, price: 3, sum: 4 };
        },
        commercialEstimateColRole(colIdx) {
            const roles = this.commercialEstimateColIndices();
            const i = Number(colIdx);
            if (i === roles.unit) return 'unit';
            if (i === roles.qty) return 'qty';
            if (i === roles.price) return 'price';
            if (i === roles.sum) return 'sum';
            return 'name';
        },
        commercialEstimateInputName(fieldKey, rowIdx, colIdx) {
            return `values[${fieldKey}][${rowIdx}][${colIdx}]`;
        },
        commercialEstimateUnitTypeKey(fieldKey, rowIdx) {
            return `${fieldKey}:${rowIdx}`;
        },
        measurementTypeOptionEntries() {
            return Object.entries(this.measurementMeta.typeOptions || {}).map(([code, label]) => ({
                code,
                label,
            }));
        },
        unitsForMeasurementType(type) {
            const list = this.measurementMeta.unitsByType?.[type];
            return Array.isArray(list) && list.length ? list : [this.measurementMeta.defaultUnit || 'шт'];
        },
        resolveMeasurementTypeForUnit(unitCode) {
            const code = String(unitCode || '').trim();
            if (!code) {
                return this.measurementMeta.defaultType || 'piece';
            }
            return this.measurementMeta.unitToType?.[code] || this.measurementMeta.defaultType || 'piece';
        },
        defaultUnitForType(type) {
            const units = this.unitsForMeasurementType(type);
            return units[0] || this.measurementMeta.defaultUnit || 'шт';
        },
        commercialEstimateUnitType(fieldKey, rowIdx) {
            const k = this.commercialEstimateUnitTypeKey(fieldKey, rowIdx);
            if (!this.commercialEstimateUnitTypes[k]) {
                const roles = this.commercialEstimateColIndices();
                const unit = this.getCommercialEstimateCellValue(fieldKey, rowIdx, roles.unit);
                this.commercialEstimateUnitTypes[k] = this.resolveMeasurementTypeForUnit(unit);
            }
            return this.commercialEstimateUnitTypes[k];
        },
        onCommercialEstimateUnitTypeChange(fieldKey, rowIdx, type) {
            const k = this.commercialEstimateUnitTypeKey(fieldKey, rowIdx);
            this.commercialEstimateUnitTypes[k] = String(type || this.measurementMeta.defaultType || 'piece');
            const roles = this.commercialEstimateColIndices();
            this.setCommercialEstimateCellValue(
                fieldKey,
                rowIdx,
                roles.unit,
                this.defaultUnitForType(this.commercialEstimateUnitTypes[k])
            );
        },
        initCommercialEstimateUnitTypesForField(fieldKey) {
            const roles = this.commercialEstimateColIndices();
            const rowCount = this.getTableRowCount(fieldKey);
            for (let r = 0; r < rowCount; r += 1) {
                const k = this.commercialEstimateUnitTypeKey(fieldKey, r);
                const unit = this.getCommercialEstimateCellValue(fieldKey, r, roles.unit);
                const type = unit
                    ? this.resolveMeasurementTypeForUnit(unit)
                    : this.measurementMeta.defaultType || 'piece';
                this.commercialEstimateUnitTypes[k] = type;
                if (!unit) {
                    this.setCommercialEstimateCellValue(
                        fieldKey,
                        r,
                        roles.unit,
                        this.defaultUnitForType(type)
                    );
                }
            }
        },
        parseCommercialEstimateNumber(raw) {
            const s = String(raw ?? '')
                .replace(/\s+/g, '')
                .replace(',', '.')
                .trim();
            if (s === '') return 0;
            const n = Number(s);
            return Number.isFinite(n) && n >= 0 ? n : 0;
        },
        formatCommercialEstimateAmount(n) {
            const v = Math.round(Number(n) * 100) / 100;
            if (!Number.isFinite(v)) return '0';
            return String(v)
                .replace(/(\.\d*?[1-9])0+$/u, '$1')
                .replace(/\.0+$/u, '');
        },
        getCommercialEstimateCellValue(fieldKey, rowIdx, colIdx) {
            const root = this.$root && typeof this.$root.querySelector === 'function' ? this.$root : document;
            const name = this.commercialEstimateInputName(fieldKey, rowIdx, colIdx);
            const el = root.querySelector(`[name="${name}"]`);
            return el ? String(el.value ?? '') : '';
        },
        setCommercialEstimateCellValue(fieldKey, rowIdx, colIdx, value) {
            const root = this.$root && typeof this.$root.querySelector === 'function' ? this.$root : document;
            const name = this.commercialEstimateInputName(fieldKey, rowIdx, colIdx);
            const el = root.querySelector(`[name="${name}"]`);
            if (el) {
                el.value = value == null ? '' : String(value);
            }
        },
        sanitizeCommercialEstimateNumericInput(event) {
            const el = event.target;
            if (!el) return;
            let v = String(el.value ?? '').replace(/[^\d.,]/g, '');
            const parts = v.replace(/,/g, '.').split('.');
            if (parts.length > 2) {
                v = `${parts.shift()}.${parts.join('')}`;
            } else {
                v = parts.join('.');
            }
            el.value = v;
        },
        onCommercialEstimateNumericInput(fieldKey, rowIdx, colIdx, event) {
            this.sanitizeCommercialEstimateNumericInput(event);
            this.recalculateCommercialEstimateRow(fieldKey, rowIdx);
            this.recalculateCommercialEstimateGrandTotal();
        },
        commercialEstimateRowSum(fieldKey, rowIdx) {
            const roles = this.commercialEstimateColIndices();
            const qty = this.parseCommercialEstimateNumber(
                this.getCommercialEstimateCellValue(fieldKey, rowIdx, roles.qty)
            );
            const price = this.parseCommercialEstimateNumber(
                this.getCommercialEstimateCellValue(fieldKey, rowIdx, roles.price)
            );
            return qty * price;
        },
        recalculateCommercialEstimateRow(fieldKey, rowIdx) {
            const roles = this.commercialEstimateColIndices();
            const sum = this.commercialEstimateRowSum(fieldKey, rowIdx);
            this.setCommercialEstimateCellValue(
                fieldKey,
                rowIdx,
                roles.sum,
                this.formatCommercialEstimateAmount(sum)
            );
        },
        recalculateCommercialEstimateAll() {
            const field = this.fields.find((f) => this.isCommercialEstimateField(f));
            if (!field?.key) {
                return;
            }
            const key = String(field.key);
            const rowCount = this.getTableRowCount(key);
            for (let r = 0; r < rowCount; r += 1) {
                this.recalculateCommercialEstimateRow(key, r);
            }
            this.recalculateCommercialEstimateGrandTotal();
        },
        recalculateCommercialEstimateGrandTotal() {
            const field = this.fields.find((f) => this.isCommercialEstimateField(f));
            if (!field?.key) {
                return 0;
            }
            const key = String(field.key);
            const rowCount = this.getTableRowCount(key);
            let total = 0;
            for (let r = 0; r < rowCount; r += 1) {
                total += this.commercialEstimateRowSum(key, r);
            }
            const formatted = this.formatCommercialEstimateAmount(total);
            this.commercialEstimateGrandTotalFormatted = formatted;
            this.syncCommercialEstimateTotalFields(formatted);
            return total;
        },
        syncCommercialEstimateTotalFields(formatted) {
            for (const key of ['итого_оборудование', 'итого_вся_смета']) {
                const root = this.$root && typeof this.$root.querySelector === 'function' ? this.$root : document;
                const el = root.querySelector(`[name="values[${key}]"]`);
                if (el) {
                    el.value = formatted;
                }
            }
        },
        setCommercialEstimateRowCount(fieldKey, value) {
            this.setTableRowCount(fieldKey, value);
            this.$nextTick(() => {
                this.initCommercialEstimateUnitTypesForField(fieldKey);
                this.recalculateCommercialEstimateAll();
            });
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
        findFieldByKey(fieldKey) {
            const key = String(fieldKey || '').trim();
            if (!key) {
                return null;
            }

            return (this.fields || []).find((x) => x.key === key) || null;
        },
        isActiveFieldTable() {
            const f = this.findFieldByKey(this.activeEditorFieldKey);

            return !!(f && f.type === 'table');
        },
        activeFieldInsertButtonLabel() {
            return this.isActiveFieldTable() ? 'Вставить в таблицу' : 'Вставить в активное поле';
        },
        mapTableColumnRoles(columns) {
            const roles = { seq: -1, name: -1, quantity: -1, note: -1 };
            const list = Array.isArray(columns) ? columns : [];
            list.forEach((col, i) => {
                const h = String(col || '').toLowerCase();
                if (/п\/?п|№\s*п|номер\s*п/i.test(h)) {
                    roles.seq = i;
                } else if (/наименование|запасн|составн|част|оборудован/i.test(h)) {
                    roles.name = i;
                } else if (/кол-?во|количество|\(шт/i.test(h)) {
                    roles.quantity = i;
                } else if (/примечан/i.test(h)) {
                    roles.note = i;
                }
            });
            if (roles.name < 0 && list.length >= 2) {
                if (roles.seq < 0) {
                    roles.seq = 0;
                }
                if (roles.name < 0) {
                    roles.name = 1;
                }
                if (roles.quantity < 0 && list.length > 2) {
                    roles.quantity = 2;
                }
                if (roles.note < 0 && list.length > 3) {
                    roles.note = 3;
                }
            }

            return roles;
        },
        setTableCellValue(fieldKey, rowIdx, colIdx, value) {
            const key = String(fieldKey);
            const name = `values[${key}][${rowIdx}][${colIdx}]`;
            const root = this.$root && typeof this.$root.querySelector === 'function' ? this.$root : document;
            const node = root.querySelector(`[name="${name}"]`);
            if (node) {
                node.value = value == null ? '' : String(value);
            }
        },
        fillTableFieldFromEquipment(field, rawRows) {
            if (!field || field.type !== 'table' || !field.key) {
                return false;
            }
            const normalized = (Array.isArray(rawRows) ? rawRows : [])
                .map((r) => this.normalizeEquipmentRow(this.cleanEquipmentPayloadRow(r)))
                .filter(Boolean);
            if (normalized.length === 0) {
                return false;
            }
            const fieldKey = String(field.key);
            const columns = Array.isArray(field.table_columns) ? field.table_columns : [];
            const roles = this.mapTableColumnRoles(columns);
            const rowCount = Math.min(30, normalized.length);
            this.setTableRowCount(fieldKey, rowCount);
            this.$nextTick(() => {
                for (let r = 0; r < rowCount; r++) {
                    const eq = normalized[r];
                    if (roles.seq >= 0) {
                        this.setTableCellValue(fieldKey, r, roles.seq, String(r + 1));
                    }
                    if (roles.name >= 0) {
                        this.setTableCellValue(fieldKey, r, roles.name, eq.name || eq.line);
                    }
                    if (roles.quantity >= 0) {
                        this.setTableCellValue(fieldKey, r, roles.quantity, eq.quantity);
                    }
                }
            });

            return true;
        },
        collectSelectedEquipmentRows() {
            const rawEquipment = String(this.selectedApplicationEquipment || '').trim();
            if (!rawEquipment) {
                window.alert('Сначала выберите оборудование из заявки.');

                return null;
            }
            if (rawEquipment === '__ALL__') {
                if (!Array.isArray(this.selectedApplicationIds) || this.selectedApplicationIds.length === 0) {
                    window.alert('Отметьте одну или несколько заявок (или нажмите «Все заявки»).');

                    return null;
                }
                const rows = this.selectedApplicationEquipmentOptions();
                if (!Array.isArray(rows) || rows.length === 0) {
                    window.alert('В выбранных заявках нет строк оборудования.');

                    return null;
                }

                return rows;
            }
            let equipmentRow = null;
            try {
                equipmentRow = JSON.parse(rawEquipment);
            } catch (e) {
                equipmentRow = null;
            }
            const one = this.normalizeEquipmentRow(equipmentRow);
            if (!one) {
                window.alert('Не удалось прочитать выбранную позицию оборудования.');

                return null;
            }

            return [equipmentRow];
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
            const rows = this.collectSelectedEquipmentRows();
            if (!rows) {
                return;
            }
            const key = String(this.activeEditorFieldKey || '').trim();
            if (!key) {
                window.alert('Сначала кликните в таблицу или текстовое поле, куда вставить оборудование.');
                return;
            }
            const f = this.fields.find((x) => x.key === key);
            if (!f) {
                window.alert('Активное поле не найдено.');
                return;
            }
            if (f.type === 'table') {
                if (!this.fillTableFieldFromEquipment(f, rows)) {
                    window.alert('Не удалось заполнить таблицу.');
                }
                return;
            }
            if (f.type !== 'text' && f.type !== 'textarea') {
                window.alert('Выберите текстовое поле или таблицу для вставки.');
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
                            const sel = `[name="values[${key}][${r}][${c}]"]`;
                            const node = root.querySelector(sel);
                            if (node && 'value' in node) {
                                node.value = cell !== null && cell !== undefined ? String(cell) : '';
                            }
                        }
                    }
                    if (this.isCommercialEstimateField(f)) {
                        this.$nextTick(() => {
                            this.initCommercialEstimateUnitTypesForField(key);
                            this.recalculateCommercialEstimateAll();
                        });
                    }
                    continue;
                }
                const val = raw !== null && raw !== undefined && typeof raw !== 'object' ? String(raw) : '';
                if (f.type === 'text' || f.type === 'textarea' || f.type === 'subdivision_warehouse') {
                    const el = document.getElementById(`editor-${f.slug}`);
                    const h = document.getElementById(`hidden-${f.slug}`);
                    if (el) {
                        el.innerHTML = val;
                    }
                    if (h) {
                        h.value = val;
                    }
                    if (f.type === 'subdivision_warehouse') {
                        const ref = String(data._подразделение_ref || '').trim();
                        if (ref !== '') {
                            this.subdivisionWarehouseRef = ref;
                        }
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
        async submit(ev) {
            this.prepareLayoutFormBeforeSubmit();
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
                let message = 'Проверьте заполнение полей.';
                try {
                    message = this.formatValidationErrors(await res.json());
                } catch (e) {
                    /* ignore */
                }
                window.alert(message);
                return;
            }
            if (res.ok && ct.includes('application/json')) {
                let payload = null;
                try {
                    payload = await res.json();
                } catch (e) {
                    payload = null;
                }
                if (payload && typeof payload.redirect === 'string' && payload.redirect !== '') {
                    window.location.href = payload.redirect;
                    return;
                }
            }
            if (res.ok && ct.includes('application/pdf')) {
                if (this.submitRedirectsOnSuccess) {
                    window.alert('Не удалось сохранить коммерческое предложение. Повторите попытку.');
                    return;
                }
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
