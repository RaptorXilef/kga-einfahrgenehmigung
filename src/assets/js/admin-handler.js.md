/**
 * UI-Handler für das administrative Dashboard.
 *
 * Steuert das Client-seitige Tab-Switching inklusive Zustandsspeicherung (localStorage),
 * die Echtzeit-Tabellenfilterung bei Suchen, dynamische Formular-Sichtbarkeiten,
 * den administrativen Workflow für Genehmigungssperren über Prompts sowie
 * die Permission-Matrix.
 *
 * Path: src/assets/js/admin-handler.js
 */
class AdminDashboardHandler {
    constructor() {
        this.tabs = document.querySelectorAll('[data-tab-target]');
        this.contents = document.querySelectorAll('.c-tabs__content');
        this.searchInput = document.getElementById('adminSearch');
        this.templateSelect = document.getElementById('manual_template_key');

        this.init();
        this.restoreLastTab();
    }

    init() {
        // 1. Tab-Steuerung
        this.tabs.forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchTab(btn.getAttribute('data-tab-target'), btn);
            });
        });

        // 2. Server-Side Such-Logik (Debounce)
        if (this.searchInput) {
            let debounceTimer;
            this.searchInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    document.getElementById('dashboardFilterForm').submit();
                }, 600);
            });

            if (this.searchInput.value) {
                const val = this.searchInput.value;
                this.searchInput.value = '';
                this.searchInput.value = val;
                this.searchInput.focus();
            }
        }

        // 3. Vorlagen-Wechsel
        if (this.templateSelect) {
            this.templateSelect.addEventListener('change', (e) => {
                const wrapper = document.getElementById('custom_end_wrapper');
                if (wrapper) {
                    wrapper.style.display = e.target.value.includes('custom') ? 'block' : 'none';
                }
            });
        }

        // 4. Delegierte Klicks (Sperren)
        document.addEventListener('click', (e) => {
            const suspendBtn = e.target.closest('.js-suspend-btn');
            if (suspendBtn) {
                e.preventDefault();
                const code = suspendBtn.getAttribute('data-code');
                const reason = prompt(`Grund für die Sperre von ${code}?`);

                if (reason && reason.trim() !== '') {
                    const form = document.getElementById(`form_suspend_${code}`);
                    const input = document.getElementById(`reason_suspend_${code}`);
                    if (form && input) {
                        input.value = reason;
                        form.submit();
                    }
                }
            }
        });

        // 5. Rechte-Matrix initialisieren
        this.initPermissionMatrix();
    }

    /**
     * Initialisiert die Logik für die Rollen-Matrix (TwoKinds Standard)
     */
    initPermissionMatrix() {
        // Initiales Setup für den Master-Toggle (Gott-Modus)
        document.querySelectorAll('.permission-container').forEach((container) => {
            const masterCb = container.querySelector('[data-master-toggle="true"]');
            if (masterCb) this.applyMasterState(container, masterCb.checked);
        });

        // WICHTIG: Event Delegation nur einmal registrieren (verhindert Ghost-Clicks)
        if (!window.permissionMatrixBound) {
            document.addEventListener('change', (e) => {
                if (e.target.matches('[data-perm-check="true"]')) {
                    this.handlePermissionChange(e.target);
                } else if (e.target.matches('[data-master-toggle="true"]')) {
                    this.applyMasterState(
                        e.target.closest('.permission-container'),
                        e.target.checked
                    );
                }
            });
            window.permissionMatrixBound = true;
        }
    }

    /**
     * Gott-Modus (*): Graut den gesamten Baum aus und sperrt die Bedienung.
     */
    applyMasterState(container, isMaster) {
        const treeWrapper = container.querySelector('.p-tree-wrapper');
        if (!treeWrapper) return;

        if (isMaster) {
            treeWrapper.classList.add('is-master-active');
            treeWrapper.querySelectorAll('input[data-perm-check="true"]').forEach((cb) => {
                cb.disabled = true;
                cb.parentElement.style.pointerEvents = 'none';
            });
        } else {
            treeWrapper.classList.remove('is-master-active');
            treeWrapper.querySelectorAll('input[data-perm-check="true"]').forEach((cb) => {
                cb.disabled = false;
                cb.parentElement.style.pointerEvents = 'auto';
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

    switchTab(tabId, activeBtn) {
        if (!tabId || !activeBtn) return;
        this.contents.forEach((c) => c.classList.remove('c-tabs__content--active'));
        this.tabs.forEach((b) => b.classList.remove('c-tabs__btn--active'));

        const target = document.getElementById(tabId);
        if (target) {
            target.classList.add('c-tabs__content--active');
            activeBtn.classList.add('c-tabs__btn--active');
            localStorage.setItem('lastAdminTab', tabId);
        }
    }

    restoreLastTab() {
        const lastTab = localStorage.getItem('lastAdminTab') || 'tab-active';
        const targetBtn = document.querySelector(`[data-tab-target="${lastTab}"]`);
        if (targetBtn) {
            this.switchTab(lastTab, targetBtn);
        }
    }
}

// Initialisierung (Sicherstellen, dass DOM bereit ist)
const startHandler = () => {
    if (!window.adminHandlerInstance) {
        window.adminHandlerInstance = new AdminDashboardHandler();
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startHandler);
} else {
    startHandler();
}
