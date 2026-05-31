// скрипт на странице
const DEBOUNCE_MS = 450;

function requestSubmitForm(form) {
    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
    } else {
        form.submit();
    }
}

export function bindAutoSubmitFilterForms(root = document) {
    root.querySelectorAll('form[data-auto-submit="filter"]').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'get') {
            return;
        }
        if (form.dataset.autoSubmitBound === '1') {
            return;
        }
        form.dataset.autoSubmitBound = '1';

        let searchTimer = null;

        function submitForm() {
            if (searchTimer !== null) {
                clearTimeout(searchTimer);
                searchTimer = null;
            }
            requestSubmitForm(form);
        }

        function scheduleSearchSubmit() {
            if (searchTimer !== null) {
                clearTimeout(searchTimer);
            }
            searchTimer = window.setTimeout(() => {
                searchTimer = null;
                requestSubmitForm(form);
            }, DEBOUNCE_MS);
        }

        form.querySelectorAll('select').forEach((el) => {
            if (el.dataset.manualSubmit === '1') {
                return;
            }
            el.addEventListener('change', submitForm);
        });

        form.querySelectorAll('input[type="checkbox"][name], input[type="radio"][name]').forEach((el) => {
            el.addEventListener('change', submitForm);
        });

        const searchFields = new Set();
        form.querySelectorAll('input[type="search"][name]').forEach((el) => searchFields.add(el));
        form.querySelectorAll('input[type="text"][name="q"]').forEach((el) => searchFields.add(el));

        searchFields.forEach((el) => {
            el.addEventListener('input', scheduleSearchSubmit);
            el.addEventListener('search', () => {
                if (searchTimer !== null) {
                    clearTimeout(searchTimer);
                    searchTimer = null;
                }
                requestSubmitForm(form);
            });
            el.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') {
                    return;
                }
                e.preventDefault();
                if (searchTimer !== null) {
                    clearTimeout(searchTimer);
                    searchTimer = null;
                }
                requestSubmitForm(form);
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', () => bindAutoSubmitFilterForms());
