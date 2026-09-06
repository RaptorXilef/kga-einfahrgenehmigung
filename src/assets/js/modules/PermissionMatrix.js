/**
 * Logik-Controller für die Rechteverwaltung (Rollen & Permissions).
 * Kapselt das UI-Toggle, den Master-Switch (Gott-Modus) und die
 * Top-Down/Bottom-Up Auswahl des Rechte-Baumes.
 */
export class PermissionMatrix {
    constructor(container) {
        this.container = container;
        this.init();
    }

    init() {
        // A. UI Modus (Fokus/Experte) wiederherstellen
        const savedMode = localStorage.getItem('pref_perm_ui_mode') || 'hide';
        this.updateUiMode(savedMode);

        const radio = document.querySelector(`input[name="ui_mode_toggle"][value="${savedMode}"]`);
        if (radio) radio.checked = true;

        // B. Initialen Zustand der Matrizen (Master/Locks) berechnen
        const permissionContainers = this.container.querySelectorAll('.permission-container');
        permissionContainers.forEach((wrapper) => {
            const masterCb = wrapper.querySelector('input[data-master-toggle]');
            if (masterCb) this.applyMasterState(wrapper, masterCb.checked);
            this.refreshTreeVisually(wrapper);
        });

        // C. Event-Delegation für Checkboxen
        this.container.addEventListener('change', (e) => {
            // 1. UI Toggle wechseln
            if (e.target.name === 'ui_mode_toggle') {
                this.updateUiMode(e.target.value);
            }

            // 2. MASTER TOGGLE (Gott Modus)
            if (e.target.dataset.masterToggle) {
                this.applyMasterState(e.target.closest('.permission-container'), e.target.checked);
            }

            // 3. SMART TREE Logik (Einzelne Rechte)
            if (e.target.dataset.permCheck) {
                this.handleTreeLogic(e.target);
            }
        });

        // D. Fokus-Sprung ausführen (falls ?focus= in URL)
        this.handleUrlFocus();
    }

    updateUiMode(mode) {
        const wrappers = this.container.querySelectorAll('.p-tree-wrapper');
        wrappers.forEach((w) => {
            w.classList.remove('mode-hide', 'mode-grey');
            w.classList.add('mode-' + mode);
        });
        localStorage.setItem('pref_perm_ui_mode', mode);
    }

    applyMasterState(wrapper, isMaster) {
        const treeWrapper = wrapper.querySelector('.p-tree-wrapper');
        if (!treeWrapper) return;

        if (isMaster) {
            treeWrapper.classList.add('is-master-active');
            treeWrapper.querySelectorAll('input[data-perm-check]').forEach((cb) => {
                cb.disabled = true;
                if (cb.parentElement) {
                    cb.parentElement.style.opacity = '0.5';
                    cb.parentElement.style.pointerEvents = 'none';
                }
            });
        } else {
            treeWrapper.classList.remove('is-master-active');
            treeWrapper.querySelectorAll('input[data-perm-check]').forEach((cb) => {
                cb.disabled = false;
                if (cb.parentElement) {
                    cb.parentElement.style.opacity = '1';
                    cb.parentElement.style.pointerEvents = 'auto';
                }
            });
        }
    }

    refreshTreeVisually(wrapper) {
        const nodes = wrapper.querySelectorAll('.p-tree-node');
        nodes.forEach((node) => {
            const key = node.dataset.key;
            if (!key) return;

            const parentNode = node.parentElement.closest('.p-tree-node');
            if (parentNode) {
                const parentCheckbox = parentNode.querySelector(
                    ':scope > .p-item input[data-perm-check]'
                );
                if (parentCheckbox && !parentCheckbox.checked) {
                    node.classList.add('is-locked');
                } else {
                    node.classList.remove('is-locked');
                }
            }
        });
    }

    handleTreeLogic(checkbox) {
        const wrapper = checkbox.closest('.permission-container');

        if (checkbox.checked) {
            // Bottom-Up Aktivierung: Väter aktivieren, wenn Kind aktiviert wird
            let parentNode = checkbox.closest('.p-tree-node').parentElement.closest('.p-tree-node');
            while (parentNode) {
                const parentCheckbox = parentNode.querySelector(
                    ':scope > .p-item input[data-perm-check]'
                );
                if (parentCheckbox && !parentCheckbox.checked) {
                    parentCheckbox.checked = true;

                    const pItem = parentCheckbox.closest('.p-item');
                    if (pItem) {
                        pItem.classList.add('is-auto-active');
                        setTimeout(() => pItem.classList.remove('is-auto-active'), 1000);
                    }
                }
                parentNode = parentNode.parentElement.closest('.p-tree-node');
            }
        } else {
            // Top-Down Deaktivierung: Kinder deaktivieren, wenn Vater deaktiviert wird
            const currentNode = checkbox.closest('.p-tree-node');
            if (currentNode) {
                currentNode
                    .querySelectorAll('input[data-perm-check]')
                    .forEach((child) => (child.checked = false));
            }
        }

        this.refreshTreeVisually(wrapper);
    }

    handleUrlFocus() {
        const urlParams = new URLSearchParams(window.location.search);
        const focusId = urlParams.get('focus');

        if (focusId) {
            const targetCard = document.getElementById('card-' + focusId);

            if (targetCard) {
                // Tab-Wechsel falls nötig
                const parentTabContent = targetCard.closest('.c-tabs__content');
                if (parentTabContent) {
                    const tabBtn = document.querySelector(
                        `[data-tab-target="${parentTabContent.id}"]`
                    );
                    if (tabBtn) {
                        document
                            .querySelectorAll('.c-tabs__btn')
                            .forEach((b) => b.classList.remove('c-tabs__btn--active'));
                        document
                            .querySelectorAll('.c-tabs__content')
                            .forEach((c) => c.classList.remove('c-tabs__content--active'));

                        tabBtn.classList.add('c-tabs__btn--active');
                        parentTabContent.classList.add('c-tabs__content--active');
                    }
                }

                // Karte aufklappen, scrollen & Highlight
                setTimeout(() => {
                    targetCard.classList.remove('is-closed');
                    targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    targetCard.style.outline = '3px solid var(--primary-color)';
                    targetCard.style.outlineOffset = '2px';

                    setTimeout(() => {
                        targetCard.style.outline = 'none';
                    }, 2000);
                }, 400);
            }
        }
    }
}
