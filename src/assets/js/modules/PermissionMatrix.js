/**
 * Logik-Controller für die Rechteverwaltung (Rollen & Permissions).
 * Kapselt das UI-Toggle, den Master-Switch (Gott-Modus) und die
 * robuste Top-Down/Bottom-Up Auswahl des Rechte-Baumes.
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
            const masterCb = wrapper.querySelector('input[data-master-toggle="true"]');
            if (masterCb) this.applyMasterState(wrapper, masterCb.checked);
        });

        // C. Event-Delegation für Checkboxen
        this.container.addEventListener('change', (e) => {
            // 1. UI Toggle wechseln
            if (e.target.name === 'ui_mode_toggle') {
                this.updateUiMode(e.target.value);
            }

            // 2. MASTER TOGGLE (Gott Modus)
            if (e.target.matches('[data-master-toggle="true"]')) {
                this.applyMasterState(e.target.closest('.permission-container'), e.target.checked);
            }

            // 3. SMART TREE Logik (Einzelne Rechte - TwoKinds-Style)
            if (e.target.matches('[data-perm-check="true"]')) {
                this.handlePermissionChange(e.target);
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

    applyMasterState(container, isMaster) {
        const treeWrapper = container.querySelector('.p-tree-wrapper');
        if (!treeWrapper) return;

        if (isMaster) {
            treeWrapper.classList.add('is-master-active');
            treeWrapper.querySelectorAll('input[data-perm-check="true"]').forEach((cb) => {
                cb.disabled = true;
                if (cb.parentElement) cb.parentElement.style.pointerEvents = 'none';
            });
        } else {
            treeWrapper.classList.remove('is-master-active');
            treeWrapper.querySelectorAll('input[data-perm-check="true"]').forEach((cb) => {
                cb.disabled = false;
                if (cb.parentElement) cb.parentElement.style.pointerEvents = 'auto';
            });
        }
    }

    /**
     * Hilfsmethode für kurzes visuelles Feedback (Grün für An, Rot für Aus)
     */
    triggerHighlight(element, isActive) {
        if (!element) return;
        const className = isActive ? 'is-auto-active' : 'is-auto-inactive';
        element.classList.add(className);
        setTimeout(() => element.classList.remove(className), 800);
    }

    // --- DOM TRAVERSAL HELPER (Kugelsicher) ---

    /**
     * 1. Holt die exakte Checkbox für einen spezifischen Baumknoten (nur 1. Ebene)
     */
    getNodeCheckbox(node) {
        if (!node) return null;
        const pItem = Array.from(node.children).find((el) => el.classList.contains('p-item'));
        return pItem ? pItem.querySelector('input[data-perm-check="true"]') : null;
    }

    /**
     * 2. Holt STRENG nur die direkten logischen Kinder-Checkboxen eines Knotens.
     * Überspringt Kategorie-Knoten ohne eigene Checkbox intelligent.
     */
    getAllLogicalChildrenCheckboxes(parentNode) {
        let cbs = [];

        // Strenger Filter: Nur direkte HTML-Kinder (.p-tree-node), keine tieferen Suchen im DOM!
        const directChildNodes = Array.from(parentNode.children).filter((child) =>
            child.classList.contains('p-tree-node')
        );

        directChildNodes.forEach((childNode) => {
            const cb = this.getNodeCheckbox(childNode);
            if (cb) {
                cbs.push(cb);
            } else {
                // Kategorie-Knoten (z.B. "System" ohne Checkbox) -> Wir holen dessen logische Kinder
                cbs = cbs.concat(this.getAllLogicalChildrenCheckboxes(childNode));
            }
        });
        return cbs;
    }

    /**
     * 3. Bottom-Up: Klettert den Baum hoch und synchronisiert die Eltern-Knoten.
     */
    updateParents(node) {
        let parentNode = node.parentElement ? node.parentElement.closest('.p-tree-node') : null;

        while (parentNode) {
            const parentCb = this.getNodeCheckbox(parentNode);

            if (parentCb) {
                const childrenCbs = this.getAllLogicalChildrenCheckboxes(parentNode);

                if (childrenCbs.length > 0) {
                    // Ein Elternteil ist NUR DANN aktiv, wenn WIRKLICH ALLE seine direkten Kinder aktiv sind!
                    const allChecked = childrenCbs.every((cb) => cb.checked);

                    if (parentCb.checked !== allChecked) {
                        parentCb.checked = allChecked;
                        this.triggerHighlight(parentCb.closest('.p-item'), allChecked);
                    }
                }
            }

            // Klettere eine Ebene höher
            parentNode = parentNode.parentElement
                ? parentNode.parentElement.closest('.p-tree-node')
                : null;
        }
    }

    /**
     * 4. Die intelligente Logik - Adaptiert für n-Level Bäume!
     */
    handlePermissionChange(checkbox) {
        const node = checkbox.closest('.p-tree-node');
        const isChecked = checkbox.checked;

        // A) TOP-DOWN: Wenn dieser Knoten geklickt wurde, müssen alle darunterliegenden Kinder denselben Status annehmen.
        const descendantCheckboxes = node.querySelectorAll('input[data-perm-check="true"]');
        descendantCheckboxes.forEach((cb) => {
            if (cb !== checkbox && cb.checked !== isChecked) {
                cb.checked = isChecked;
                this.triggerHighlight(cb.closest('.p-item'), isChecked);
            }
        });

        // B) BOTTOM-UP: Aktualisiere alle Väter nach oben hinweg, strikt nach TwoKinds-Vorbild.
        this.updateParents(node);
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
