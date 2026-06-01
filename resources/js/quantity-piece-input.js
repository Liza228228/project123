// скрипт на странице
export const PIECE_MEASUREMENT_TYPE = 'piece';

export const CLOTHING_MEASUREMENT_TYPE = 'clothing_size';

export function isPieceMeasurementType(type) {
    return String(type || '').trim() === PIECE_MEASUREMENT_TYPE;
}

export function isClothingMeasurementType(type) {
    return String(type || '').trim() === CLOTHING_MEASUREMENT_TYPE;
}

export function requiresWholeQuantityMeasurement(type) {
    return isPieceMeasurementType(type) || isClothingMeasurementType(type);
}

const BLOCKED_DECIMAL_KEYS = new Set(['.', ',', 'e', 'E', '+', '-']);

export function pieceQtyDigitsOnly(raw) {
    return String(raw ?? '').replace(/[^\d]/g, '');
}

export function normalizePositivePieceQty(raw) {
    const digits = pieceQtyDigitsOnly(raw);
    if (digits === '') {
        return '';
    }
    const n = parseInt(digits, 10);
    if (!Number.isFinite(n) || n < 1) {
        return '';
    }

    return String(n);
}

export function sanitizePieceQuantityValue(raw) {
    return normalizePositivePieceQty(raw);
}

export function applyQuantityFieldRules(input, measurementType) {
    if (!input) {
        return;
    }
    const wholeOnly = requiresWholeQuantityMeasurement(measurementType);
    if (wholeOnly) {
        input.type = 'text';
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('pattern', '[0-9]*');
        input.setAttribute('autocomplete', 'off');
        input.removeAttribute('step');
        input.removeAttribute('min');
    } else {
        input.type = 'number';
        input.step = '0.001';
        input.min = '0.001';
        input.setAttribute('inputmode', 'decimal');
        input.removeAttribute('pattern');
    }
}

function guardWholeQuantityValue(input, getMeasurementType) {
    if (!requiresWholeQuantityMeasurement(getMeasurementType())) {
        return;
    }
    const digits = normalizePositivePieceQty(input.value);
    if (digits !== String(input.value)) {
        input.value = digits;
    }
}

export function bindPieceQuantityInput(input, getMeasurementType) {
    if (!input) {
        return;
    }

    const resolveType =
        typeof getMeasurementType === 'function' ? getMeasurementType : () => PIECE_MEASUREMENT_TYPE;

    applyQuantityFieldRules(input, resolveType());
    guardWholeQuantityValue(input, resolveType);

    if (input.dataset.pieceQtyBound === '1') {
        applyQuantityFieldRules(input, resolveType());
        guardWholeQuantityValue(input, resolveType);

        return;
    }

    input.addEventListener('keydown', (e) => {
        if (!requiresWholeQuantityMeasurement(resolveType())) {
            return;
        }
        if (BLOCKED_DECIMAL_KEYS.has(e.key)) {
            e.preventDefault();
        }
        if (
            e.key === '0' &&
            requiresWholeQuantityMeasurement(resolveType()) &&
            normalizePositivePieceQty(input.value) === ''
        ) {
            e.preventDefault();
        }
    });

    input.addEventListener('paste', (e) => {
        if (!requiresWholeQuantityMeasurement(resolveType())) {
            return;
        }
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
        input.value = normalizePositivePieceQty(text);
    });

    input.addEventListener('input', () => guardWholeQuantityValue(input, resolveType));

    input.addEventListener('blur', () => {
        if (!requiresWholeQuantityMeasurement(resolveType())) {
            return;
        }
        let digits = normalizePositivePieceQty(input.value);
        if (digits === '') {
            digits = '1';
        }
        input.value = digits;
    });

    input.dataset.pieceQtyBound = '1';
}

export function bindPieceQuantityTextInput(input, getUnitTypeCode) {
    if (!input) {
        return;
    }

    const resolveCode = typeof getUnitTypeCode === 'function' ? getUnitTypeCode : () => '';

    applyQuantityFieldRules(input, resolveCode());
    guardWholeQuantityValue(input, resolveCode);

    if (input.dataset.pieceQtyBound === '1') {
        applyQuantityFieldRules(input, resolveCode());
        guardWholeQuantityValue(input, resolveCode);

        return;
    }

    input.addEventListener('keydown', (e) => {
        if (!requiresWholeQuantityMeasurement(resolveCode())) {
            return;
        }
        if (BLOCKED_DECIMAL_KEYS.has(e.key)) {
            e.preventDefault();
        }
        if (
            e.key === '0' &&
            requiresWholeQuantityMeasurement(resolveCode()) &&
            normalizePositivePieceQty(input.value) === ''
        ) {
            e.preventDefault();
        }
    });

    input.addEventListener('paste', (e) => {
        if (!requiresWholeQuantityMeasurement(resolveCode())) {
            return;
        }
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
        input.value = normalizePositivePieceQty(text);
    });

    input.addEventListener('input', () => {
        if (!requiresWholeQuantityMeasurement(resolveCode())) {
            return;
        }
        let v = String(input.value || '').replace(/[A-Za-zА-Яа-яЁё]/g, '');
        v = normalizePositivePieceQty(v);
        if (v !== input.value) {
            input.value = v;
        }
    });

    input.addEventListener('blur', () => {
        if (!requiresWholeQuantityMeasurement(resolveCode())) {
            return;
        }
        let digits = normalizePositivePieceQty(input.value);
        if (digits === '') {
            digits = '1';
        }
        input.value = digits;
    });

    input.dataset.pieceQtyBound = '1';
}

if (typeof window !== 'undefined') {
    window.QuantityPieceInput = {
        PIECE_MEASUREMENT_TYPE,
        CLOTHING_MEASUREMENT_TYPE,
        isPieceMeasurementType,
        isClothingMeasurementType,
        requiresWholeQuantityMeasurement,
        pieceQtyDigitsOnly,
        normalizePositivePieceQty,
        applyQuantityFieldRules,
        sanitizePieceQuantityValue,
        bindPieceQuantityInput,
        bindPieceQuantityTextInput,
    };
}
