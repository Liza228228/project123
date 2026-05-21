import {
    bindPieceQuantityTextInput,
    isPieceMeasurementType,
    sanitizePieceQuantityValue,
} from './quantity-piece-input';

document.addEventListener('alpine:init', () => {
    Alpine.data('materialsReceiptEquipmentPicker', () => {
        const cfg = window.__materialsReceiptPicker;
        const items = Array.isArray(cfg?.items) ? cfg.items : [];
        const initialId =
            cfg?.initialId !== undefined && cfg?.initialId !== null && cfg.initialId !== ''
                ? Number(cfg.initialId)
                : null;

        const strictQuantityUnitTypes = ['piece', 'mass', 'length'];

        return {
            items,
            selectedId: Number.isFinite(initialId) && initialId > 0 ? initialId : null,
            search: '',
            open: false,
            equipError: false,
            receiptVariantError: false,
            blurTimer: null,
            strictQuantityUnitTypes,

            init() {
                this.syncSearchFromSelection();
                this.sanitizeReceiptQuantityField();
                const qtyEl = document.getElementById('quantity');
                bindPieceQuantityTextInput(qtyEl, () => this.selectedUnitTypeCode);
            },

            syncSearchFromSelection() {
                const hit = this.items.find((i) => Number(i.id) === Number(this.selectedId));
                this.search = hit ? hit.label : '';
            },

            get filteredItems() {
                const q = (this.search || '').trim().toLowerCase();
                if (!q) {
                    return this.items;
                }
                return this.items.filter((i) => (i.label || '').toLowerCase().includes(q));
            },

            onSearchInput() {
                this.open = true;
                this.equipError = false;
                this.receiptVariantError = false;
                const hit = this.items.find((i) => Number(i.id) === Number(this.selectedId));
                if (hit && hit.label === this.search) {
                    return;
                }
                this.selectedId = null;
            },

            onFocus() {
                window.clearTimeout(this.blurTimer);
                this.open = true;
            },

            onBlur() {
                this.blurTimer = window.setTimeout(() => {
                    this.open = false;
                    this.syncSearchFromSelection();
                }, 180);
            },

            selectItem(item) {
                this.selectedId = Number(item.id);
                this.search = item.label;
                this.open = false;
                this.equipError = false;
                this.receiptVariantError = false;
                const variantEl = document.getElementById('receipt_variant');
                if (variantEl) {
                    variantEl.selectedIndex = 0;
                }
                this.sanitizeReceiptQuantityField();
            },

            get selectedUnitTypeCode() {
                const hit = this.items.find((i) => Number(i.id) === Number(this.selectedId));
                return hit && hit.unit_type_code ? String(hit.unit_type_code) : '';
            },

            get receiptQuantityNumericOnly() {
                return this.strictQuantityUnitTypes.includes(this.selectedUnitTypeCode);
            },

            get receiptClothingMode() {
                return this.selectedUnitTypeCode === 'clothing_size';
            },

            get receiptFieldLabel() {
                const labels = {
                    clothing_size: 'Размер',
                    length: 'Длина',
                    mass: 'Масса',
                    piece: 'Штуки',
                };
                const code = this.selectedUnitTypeCode;
                if (code && labels[code]) {
                    return labels[code];
                }
                return 'Количество';
            },

            sanitizeReceiptQuantityField() {
                if (this.receiptClothingMode) {
                    return;
                }
                const el = document.getElementById('quantity');
                if (!el || !this.receiptQuantityNumericOnly) {
                    return;
                }
                let cleaned = String(el.value || '').replace(/[A-Za-zА-Яа-яЁё]/g, '');
                if (isPieceMeasurementType(this.selectedUnitTypeCode)) {
                    cleaned = sanitizePieceQuantityValue(cleaned);
                }
                if (cleaned !== el.value) {
                    el.value = cleaned;
                }
            },

            onReceiptQuantityInput(e) {
                if (!this.receiptQuantityNumericOnly || this.receiptClothingMode) {
                    return;
                }
                const el = e.target;
                let cleaned = String(el.value || '').replace(/[A-Za-zА-Яа-яЁё]/g, '');
                if (isPieceMeasurementType(this.selectedUnitTypeCode)) {
                    cleaned = sanitizePieceQuantityValue(cleaned);
                }
                if (cleaned !== el.value) {
                    el.value = cleaned;
                }
            },

            validateSubmit(e) {
                if (!this.selectedId) {
                    e.preventDefault();
                    this.equipError = true;
                    this.open = true;
                    return;
                }
                if (this.receiptClothingMode) {
                    const sel = document.getElementById('receipt_variant');
                    if (!sel || String(sel.value || '').trim() === '') {
                        e.preventDefault();
                        this.receiptVariantError = true;
                        return;
                    }
                } else if (isPieceMeasurementType(this.selectedUnitTypeCode)) {
                    const qtyEl = document.getElementById('quantity');
                    const raw = qtyEl ? String(qtyEl.value || '').trim() : '';
                    const normalized = raw.replace(',', '.');
                    const num = Number(normalized);
                    if (raw === '' || !Number.isFinite(num) || num < 1 || Math.abs(num - Math.round(num)) > 0.000001) {
                        e.preventDefault();
                        qtyEl?.focus();
                        window.alert('Для учёта в штуках укажите целое число без дробной части.');
                        return;
                    }
                }
                this.receiptVariantError = false;
            },
        };
    });
});
