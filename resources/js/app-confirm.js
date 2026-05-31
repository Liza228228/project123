// скрипт на странице
import { closeAppModal, openAppModal } from './app-modals';

const MODAL_NAME = 'app-confirm';
const BYPASS_ATTR = 'data-app-confirm-bypass';

let resolvePending = null;

function getEls() {
    return {
        title: document.getElementById('app-confirm-title'),
        message: document.getElementById('app-confirm-message'),
        confirmBtn: document.getElementById('app-confirm-ok'),
        cancelBtn: document.getElementById('app-confirm-cancel'),
    };
}

function applyOptions(options = {}) {
    const els = getEls();
    if (!els.title || !els.message || !els.confirmBtn || !els.cancelBtn) {
        return;
    }

    const variant = options.variant === 'danger' ? 'danger' : 'primary';
    const defaultTitle = variant === 'danger' ? 'Подтверждение удаления' : 'Подтверждение действия';

    els.title.textContent = options.title || defaultTitle;
    els.message.textContent = options.message || '';
    els.confirmBtn.textContent = options.confirmLabel || 'Подтвердить';
    els.cancelBtn.textContent = options.cancelLabel || 'Отмена';
    els.confirmBtn.className =
        variant === 'danger'
            ? 'ui-btn ui-btn--danger w-full sm:w-auto'
            : 'ui-btn ui-btn--primary w-full sm:w-auto';
}

function settle(confirmed) {
    const resolve = resolvePending;
    resolvePending = null;
    closeAppModal(MODAL_NAME);
    resolve?.(confirmed);
}
export function showAppConfirm(options = {}) {
    return new Promise((resolve) => {
        if (resolvePending) {
            settle(false);
        }
        resolvePending = resolve;
        applyOptions(options);
        openAppModal(MODAL_NAME);
    });
}

export function initAppConfirmModal() {
    const ok = document.getElementById('app-confirm-ok');
    const cancel = document.getElementById('app-confirm-cancel');

    ok?.addEventListener('click', () => settle(true));
    cancel?.addEventListener('click', () => settle(false));

    document.addEventListener('close-modal', (event) => {
        if (event.detail === MODAL_NAME && resolvePending) {
            settle(false);
        }
    });
}

function readConfirmOptionsFromDataset(el) {
    const variant = el.getAttribute('data-app-confirm-variant') === 'danger' ? 'danger' : 'primary';

    return {
        message: el.getAttribute('data-app-confirm') || '',
        title: el.getAttribute('data-app-confirm-title') || '',
        confirmLabel: el.getAttribute('data-app-confirm-label') || 'Подтвердить',
        cancelLabel: el.getAttribute('data-app-confirm-cancel') || 'Отмена',
        variant,
    };
}

async function confirmAndSubmitForm(form) {
    const confirmed = await showAppConfirm(readConfirmOptionsFromDataset(form));
    if (!confirmed) {
        return;
    }

    form.setAttribute(BYPASS_ATTR, '1');
    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
    } else {
        form.submit();
    }
}

function bindFormConfirm(form) {
    if (form.dataset.appConfirmBound === '1') {
        return;
    }
    form.dataset.appConfirmBound = '1';

    form.addEventListener('submit', async (event) => {
        if (form.getAttribute(BYPASS_ATTR) === '1') {
            form.removeAttribute(BYPASS_ATTR);
            return;
        }

        if (!form.getAttribute('data-app-confirm')) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        await confirmAndSubmitForm(form);
    });
}

function bindFormConfirmButton(button) {
    const form = button.closest('form[data-app-confirm]');
    if (!form || button.dataset.appConfirmBound === '1') {
        return;
    }
    button.dataset.appConfirmBound = '1';

    button.addEventListener('click', async (event) => {
        if (form.getAttribute(BYPASS_ATTR) === '1') {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        await confirmAndSubmitForm(form);
    });
}

function bindLinkConfirm(link) {
    if (link.dataset.appConfirmBound === '1') {
        return;
    }
    link.dataset.appConfirmBound = '1';

    link.addEventListener('click', async (event) => {
        event.preventDefault();
        const confirmed = await showAppConfirm(readConfirmOptionsFromDataset(link));
        if (!confirmed) {
            return;
        }

        if (link.tagName === 'A' && link.href) {
            window.location.assign(link.href);
        }
    });
}
export function migrateLegacyConfirmHandlers(root = document) {
    root.querySelectorAll('form[onsubmit]').forEach((form) => {
        const onsubmit = form.getAttribute('onsubmit') || '';
        const match = onsubmit.match(/(?:window\.)?confirm\s*\(\s*(['"])([\s\S]*?)\1\s*\)/);
        if (!match) {
            return;
        }

        if (!form.getAttribute('data-app-confirm')) {
            form.setAttribute('data-app-confirm', match[2]);
        }

        if (/удал/i.test(match[2]) && !form.getAttribute('data-app-confirm-variant')) {
            form.setAttribute('data-app-confirm-variant', 'danger');
            if (!form.getAttribute('data-app-confirm-label')) {
                form.setAttribute('data-app-confirm-label', 'Да, удалить');
            }
        }

        form.removeAttribute('onsubmit');
    });
}

export function bindAppConfirmHandlers(root = document) {
    migrateLegacyConfirmHandlers(root);
    root.querySelectorAll('form[data-app-confirm]').forEach(bindFormConfirm);
    root.querySelectorAll('form[data-app-confirm] button').forEach(bindFormConfirmButton);
    root.querySelectorAll('a[data-app-confirm]').forEach(bindLinkConfirm);
}
