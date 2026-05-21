/**
 * Правила поля количества: в штуках — только целые, без точки и запятой.
 */
export function isPieceMeasurementType(type) {
    return String(type || '').trim() === 'piece';
}

const BLOCKED_DECIMAL_KEYS = new Set(['.', ',', 'e', 'E', '+', '-']);

export function pieceQtyDigitsOnly(raw) {
    return String(raw ?? '').replace(/[^\d]/g, '');
}

export function sanitizePieceQuantityValue(raw) {
    const digits = pieceQtyDigitsOnly(raw);

    return digits === '' ? '' : digits;
}

export function applyQuantityFieldRules(input, measurementType) {
    if (!input) {
        return;
    }
    const piece = isPieceMeasurementType(measurementType);
    if (piece) {
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

function guardPieceValue(input, getMeasurementType) {
    if (!isPieceMeasurementType(getMeasurementType())) {
        return;
    }
    const digits = pieceQtyDigitsOnly(input.value);
    if (digits !== String(input.value)) {
        input.value = digits;
    }
}

export function bindPieceQuantityInput(input, getMeasurementType) {
    if (!input) {
        return;
    }

    const resolveType =
        typeof getMeasurementType === 'function' ? getMeasurementType : () => 'piece';

    applyQuantityFieldRules(input, resolveType());
    guardPieceValue(input, resolveType);

    if (input.dataset.pieceQtyBound === '1') {
        return;
    }

    input.addEventListener('keydown', (e) => {
        if (!isPieceMeasurementType(resolveType())) {
            return;
        }
        if (BLOCKED_DECIMAL_KEYS.has(e.key)) {
            e.preventDefault();
        }
    });

    input.addEventListener('paste', (e) => {
        if (!isPieceMeasurementType(resolveType())) {
            return;
        }
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
        input.value = pieceQtyDigitsOnly(text);
    });

    input.addEventListener('input', () => guardPieceValue(input, resolveType));

    input.addEventListener('blur', () => {
        if (!isPieceMeasurementType(resolveType())) {
            return;
        }
        let digits = pieceQtyDigitsOnly(input.value);
        if (digits === '' || parseInt(digits, 10) < 1) {
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

    if (input.dataset.pieceQtyBound === '1') {
        guardPieceValue(input, resolveCode);
        return;
    }

    input.addEventListener('keydown', (e) => {
        if (!isPieceMeasurementType(resolveCode())) {
            return;
        }
        if (BLOCKED_DECIMAL_KEYS.has(e.key)) {
            e.preventDefault();
        }
    });

    input.addEventListener('paste', (e) => {
        if (!isPieceMeasurementType(resolveCode())) {
            return;
        }
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
        let v = pieceQtyDigitsOnly(text);
        input.value = v;
    });

    input.addEventListener('input', () => {
        if (!isPieceMeasurementType(resolveCode())) {
            return;
        }
        let v = String(input.value || '').replace(/[A-Za-zА-Яа-яЁё]/g, '');
        v = pieceQtyDigitsOnly(v);
        if (v !== input.value) {
            input.value = v;
        }
    });

    input.dataset.pieceQtyBound = '1';
}

if (typeof window !== 'undefined') {
    window.QuantityPieceInput = {
        isPieceMeasurementType,
        pieceQtyDigitsOnly,
        applyQuantityFieldRules,
        sanitizePieceQuantityValue,
        bindPieceQuantityInput,
        bindPieceQuantityTextInput,
    };
}
