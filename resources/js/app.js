import './bootstrap';
import './auto-submit-filter-forms';
import { bindAppConfirmHandlers, initAppConfirmModal, showAppConfirm } from './app-confirm';
import { bindAppModalTriggers, closeAppModal, openAppModal } from './app-modals';

import Alpine from 'alpinejs';
import './materials-receipt-equipment-picker';
import './quantity-piece-input';
import './installation-act-photo-gallery';
import { bindThemeToggles } from './theme-toggle';
import { layoutTokenEditorMixin } from './layout-template-token-editor';
import { registerLayoutApplicationCreate } from './layout-application-create';

window.Alpine = Alpine;
window.layoutTokenEditorMixin = layoutTokenEditorMixin;
window.openAppModal = openAppModal;
window.closeAppModal = closeAppModal;
window.showAppConfirm = showAppConfirm;

registerLayoutApplicationCreate(Alpine);

Alpine.start();
initAppConfirmModal();
bindThemeToggles();
document.addEventListener('DOMContentLoaded', () => {
    bindAppModalTriggers();
    bindAppConfirmHandlers();
});
bindAppModalTriggers();
bindAppConfirmHandlers();
