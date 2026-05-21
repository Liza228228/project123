/**
 * Открытие/закрытие x-modal без привязки к дереву Alpine (кнопки в header slot и т.п.).
 */
export function openAppModal(name) {
    if (!name) {
        return;
    }
    document.dispatchEvent(
        new CustomEvent('open-modal', { bubbles: true, detail: name })
    );
}

export function closeAppModal(name) {
    if (!name) {
        return;
    }
    document.dispatchEvent(
        new CustomEvent('close-modal', { bubbles: true, detail: name })
    );
}

export function bindAppModalTriggers(root = document) {
    root.querySelectorAll('[data-app-open-modal]').forEach((el) => {
        if (el.dataset.appOpenModalBound === '1') {
            return;
        }
        el.dataset.appOpenModalBound = '1';
        el.addEventListener('click', (event) => {
            event.preventDefault();
            openAppModal(el.getAttribute('data-app-open-modal'));
        });
    });
}
