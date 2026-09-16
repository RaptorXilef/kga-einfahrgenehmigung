/**
 * Logik-Controller für die Rechteverwaltung (Rollen & Permissions).
 * Kapselt das UI-Toggle, den Master-Switch (Gott-Modus) und die
 * robuste Top-Down/Bottom-Up Auswahl des Rechte-Baumes.
 */
export class PermissionMatrix {
    constructor(container) {
        this.container = container;
        this.abortController = new AbortController();

        // A11y: Visuell versteckter Announcer für Screenreader einrichten
        this.a11yAnnouncer = document.createElement('div');
        this.a11yAnnouncer.className = 'u-visually-hidden';
        this.a11yAnnouncer.setAttribute('aria-live', 'polite');
        this.a11yAnnouncer.setAttribute('aria-atomic', 'true');
        this.container.appendChild(this.a11yAnnouncer);

        this.init();
    }

    init() {
        // Initiale Master-States berechnen
        const permissionContainers = this.container.querySelectorAll('.js-permission-form');
        permissionContainers.forEach((wrapper) => {
            const masterCb = wrapper.querySelector('input[data-master-toggle="true"]');
            if (masterCb) this.applyMasterState(wrapper, masterCb.checked);
        });

        this.container.addEventListener(
            'change',
            (e) => {
                // MASTER TOGGLE (Gott Modus)
                if (e.target.matches('[data-master-toggle="true"]')) {
                    this.applyMasterState(
                        e.target.closest('.js-permission-form'),
                        e.target.checked
                    );
                }

                // SMART TREE Logik
                if (e.target.matches('[data-perm-check="true"]')) {
                    this.handlePermissionChange(e.target);
                }
            },
            { signal: this.abortController.signal }
        );

        // Fokus-Sprung ausführen (falls ?focus= in URL)
        this.handleUrlFocus();
    }

    applyMasterState(container, isMaster) {
        const treeWrapper = container.querySelector('.c-tree-wrapper');
        if (!treeWrapper) return;

        treeWrapper.classList.toggle('is-master-active', isMaster);

        treeWrapper.querySelectorAll('input[data-perm-check="true"]').forEach((cb) => {
            cb.disabled = isMaster;
        });
    }

    /**
     * Hilfsmethode für kurzes visuelles Feedback (Grün für An, Rot für Aus)
     */
    triggerHighlight(element, isActive, labelText = '') {
        if (!element) return;
        const className = isActive ? 'is-auto-active' : 'is-auto-inactive';
        element.classList.add(className);

        // A11y Feedback für den Screenreader generieren
        if (labelText) {
            const stateText = isActive ? 'aktiviert' : 'deaktiviert';
            this.a11yAnnouncer.textContent = `Abhängige Berechtigung ${labelText} wurde automatisch ${stateText}.`;
        }

        setTimeout(() => element.classList.remove(className), 800);
    }

    // --- DOM TRAVERSAL HELPER (Kugelsicher) ---

    /**
     * 1. Holt die exakte Checkbox für einen spezifischen Baumknoten (nur 1. Ebene)
     */
    getNodeCheckbox(node) {
        if (!node) return null;
        const pItem = Array.from(node.children).find((el) => el.classList.contains('c-tree-item'));
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
            child.classList.contains('c-tree-node')
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
        let parentNode = node.parentElement ? node.parentElement.closest('.c-tree-node') : null;

        while (parentNode) {
            const parentCb = this.getNodeCheckbox(parentNode);

            if (parentCb) {
                const childrenCbs = this.getAllLogicalChildrenCheckboxes(parentNode);

                if (childrenCbs.length > 0) {
                    // Ein Elternteil ist NUR DANN aktiv, wenn WIRKLICH ALLE seine direkten Kinder aktiv sind!
                    const allChecked = childrenCbs.every((cb) => cb.checked);

                    if (parentCb.checked !== allChecked) {
                        parentCb.checked = allChecked;
                        const labelText = parentCb.value; // Der Key der Berechtigung
                        this.triggerHighlight(
                            parentCb.closest('.c-tree-item'),
                            allChecked,
                            labelText
                        );
                    }
                }
            }

            // Klettere eine Ebene höher
            parentNode = parentNode.parentElement
                ? parentNode.parentElement.closest('.c-tree-node')
                : null;
        }
    }

    /**
     * 4. Die intelligente Logik - Adaptiert für n-Level Bäume!
     */
    handlePermissionChange(checkbox) {
        const node = checkbox.closest('.c-tree-node');
        const isChecked = checkbox.checked;

        // A) TOP-DOWN: Wenn dieser Knoten geklickt wurde, müssen alle darunterliegenden Kinder denselben Status annehmen.
        const descendantCheckboxes = node.querySelectorAll('input[data-perm-check="true"]');
        descendantCheckboxes.forEach((cb) => {
            if (cb !== checkbox && cb.checked !== isChecked) {
                cb.checked = isChecked;
                this.triggerHighlight(cb.closest('.c-tree-item'), isChecked, cb.value);
            }
        });

        // B) BOTTOM-UP: Aktualisiere alle Väter nach oben hinweg, strikt nach TwoKinds-Vorbild.
        this.updateParents(node);
    }

    handleUrlFocus() {
        const urlParams = new URLSearchParams(window.location.search);
        const focusId = urlParams.get('focus');

        if (focusId) {
            const targetCard = document.getElementById(`card-${focusId}`);

            if (targetCard) {
                // Tab-Wechsel falls nötig
                const parentTabContent = targetCard.closest('.c-tabs__content');
                if (parentTabContent) {
                    const tabBtn = document.querySelector(
                        `[data-tab-target="${parentTabContent.id}"]`
                    );
                    if (tabBtn) {
                        document.querySelectorAll('.c-tabs__btn').forEach((b) => {
                            b.classList.remove('is-active');
                        });
                        document.querySelectorAll('.c-tabs__content').forEach((c) => {
                            c.classList.remove('is-active');
                        });

                        tabBtn.classList.add('is-active');
                        parentTabContent.classList.add('is-active');
                    }
                }

                // Karte aufklappen, scrollen & Highlight
                setTimeout(() => {
                    targetCard.classList.remove('is-closed');
                    targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    targetCard.classList.add('is-highlighted');

                    setTimeout(() => {
                        targetCard.classList.remove('is-highlighted');
                    }, 2000);
                }, 400);
            }
        }
    }

    destroy() {
        this.abortController.abort();
        if (this.a11yAnnouncer) this.a11yAnnouncer.remove();
    }
}
