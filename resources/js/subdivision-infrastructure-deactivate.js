import Alpine from 'alpinejs';
import { openAppModal } from './app-modals';

export const DETACH_ONLY_VALUE = 'detach';

function subdivisionDeactivateModalRoot() {
    return document.querySelector('[data-subdivision-deactivate-modal-root]');
}

export function openSubdivisionDeactivateFlow(detail) {
    const root = subdivisionDeactivateModalRoot();
    if (root && typeof Alpine !== 'undefined') {
        const component = Alpine.$data(root);
        if (component && typeof component.openForSubdivision === 'function') {
            component.openForSubdivision(detail);

            return;
        }
    }

    window.dispatchEvent(
        new CustomEvent('open-subdivision-deactivate', {
            detail,
            bubbles: true,
        })
    );
}

export function registerSubdivisionDeactivateModal() {
    Alpine.data('subdivisionDeactivateModal', () => ({
        detachOnlyValue: DETACH_ONLY_VALUE,
        loading: false,
        previewError: '',
        subdivisionName: '',
        deactivateUrl: '',
        hardBlock: null,
        requiresStaffActions: false,
        boilerChiefs: [],
        foremen: [],
        chiefAssignments: {},
        foremanAssignments: {},
        get modalTitle() {
            if (this.hardBlock) {
                return 'Действие недоступно';
            }

            return 'Сделать подразделение недоступным?';
        },
        get canConfirmDeactivate() {
            if (this.hardBlock) {
                return false;
            }
            if (!this.requiresStaffActions) {
                return true;
            }

            const chiefsOk = this.boilerChiefs.every((chief) => {
                const value = String(this.chiefAssignments[chief.user_id] ?? '');

                return value !== '';
            });
            const foremenOk = this.foremen.every((foreman) => {
                const value = String(this.foremanAssignments[foreman.user_id] ?? '');

                return value !== '';
            });

            return chiefsOk && foremenOk;
        },
        defaultAssignmentForUser(user) {
            return user.has_other_subdivisions ? DETACH_ONLY_VALUE : '';
        },
        async openForSubdivision(detail) {
            if (!detail || !detail.previewUrl) {
                return;
            }

            this.loading = true;
            this.previewError = '';
            this.hardBlock = null;
            this.requiresStaffActions = false;
            this.boilerChiefs = [];
            this.foremen = [];
            this.chiefAssignments = {};
            this.foremanAssignments = {};
            this.subdivisionName = detail.name || '';
            this.deactivateUrl = detail.deactivateUrl || '';

            try {
                const response = await fetch(detail.previewUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error('Не удалось проверить возможность отключения.');
                }
                const data = await response.json();
                this.subdivisionName = data.subdivision_name || this.subdivisionName;
                this.hardBlock = data.hard_block || null;
                this.requiresStaffActions = Boolean(data.requires_staff_actions);
                this.boilerChiefs = Array.isArray(data.boiler_chiefs) ? data.boiler_chiefs : [];
                this.foremen = Array.isArray(data.foremen) ? data.foremen : [];
                this.boilerChiefs.forEach((chief) => {
                    this.chiefAssignments[chief.user_id] = this.defaultAssignmentForUser(chief);
                });
                this.foremen.forEach((foreman) => {
                    this.foremanAssignments[foreman.user_id] = this.defaultAssignmentForUser(foreman);
                });
            } catch (error) {
                this.previewError = error?.message || 'Ошибка проверки.';
            } finally {
                this.loading = false;
                openAppModal('confirm-subdivision-deactivate');
            }
        },
        confirmDeactivate() {
            const form = document.getElementById('subdivision-deactivate-form');
            const fields = document.getElementById('subdivision-deactivate-form-fields');
            if (!form || !fields || !this.deactivateUrl) {
                return;
            }
            form.action = this.deactivateUrl;
            fields.innerHTML = '';

            this.boilerChiefs.forEach((chief) => {
                const value = this.chiefAssignments[chief.user_id];
                if (value === undefined || value === null || value === '') {
                    return;
                }
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `chief_subdivisions[${chief.user_id}]`;
                input.value = value;
                fields.appendChild(input);
            });

            this.foremen.forEach((foreman) => {
                const value = this.foremanAssignments[foreman.user_id];
                if (value === undefined || value === null || value === '') {
                    return;
                }
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `foreman_subdivisions[${foreman.user_id}]`;
                input.value = value;
                fields.appendChild(input);
            });

            form.submit();
        },
    }));
}

export function bindSubdivisionDeactivateTriggers(root = document) {
    root.querySelectorAll('[data-subdivision-deactivate-trigger]').forEach((button) => {
        if (button.dataset.subdivisionDeactivateBound === '1') {
            return;
        }
        button.dataset.subdivisionDeactivateBound = '1';

        button.addEventListener('click', (event) => {
            event.preventDefault();
            const raw = button.getAttribute('data-subdivision-deactivate-payload') || '';
            if (raw === '') {
                return;
            }

            let detail;
            try {
                detail = JSON.parse(raw);
            } catch {
                return;
            }

            openSubdivisionDeactivateFlow(detail);
        });
    });

}
