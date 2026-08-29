/**
 * UI-Handler für das administrative Dashboard.
 *
 * Steuert das Client-seitige Tab-Switching inklusive Zustandsspeicherung (localStorage),
 * die Echtzeit-Tabellenfilterung bei Suchen, dynamische Formular-Sichtbarkeiten,
 * den administrativen Workflow für Genehmigungssperren über Prompts sowie
 * die Permission-Matrix und den 2-Phasen System-Update-Prozess.
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

        // 4. Delegierte Klicks (Sperren & Update)
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
                return;
            }

            const updateBtn = e.target.closest('.js-run-update-btn');
            if (updateBtn) {
                e.preventDefault();
                const zipUrl = updateBtn.getAttribute('data-url');
                const csrfToken = updateBtn.getAttribute('data-csrf');

                if (
                    confirm(
                        'Möchten Sie das Update jetzt wirklich installieren? Das System geht für kurze Zeit in den Wartungsmodus.'
                    )
                ) {
                    this.handleSystemUpdate(updateBtn, zipUrl, csrfToken);
                }
            }
        });

        // 5. Rechte-Matrix initialisieren
        this.initPermissionMatrix();
    }

    /**
     * Initialisiert die Logik für die Rollen-Matrix (Parent/Child-Abhängigkeiten)
     */
    initPermissionMatrix() {
        // Initiales Setup für den Master-Toggle (Gott-Modus)
        document.querySelectorAll('.permission-container').forEach((container) => {
            const masterCb = container.querySelector('[data-master-toggle="true"]');
            if (masterCb) this.applyMasterState(container, masterCb.checked);
        });

        // Zentrale Event-Delegation für alle Matrix-Klicks
        document.addEventListener('change', (e) => {
            if (e.target.matches('[data-perm-check="true"]')) {
                this.handlePermissionChange(e.target);
            } else if (e.target.matches('[data-master-toggle="true"]')) {
                this.applyMasterState(e.target.closest('.permission-container'), e.target.checked);
            }
        });
    }

    /**
     * Steuert den "Gott-Modus" (*). Graut den Baum aus und sperrt die Bedienung.
     */
    applyMasterState(container, isMaster) {
        const treeWrapper = container.querySelector('.p-tree-wrapper');
        if (!treeWrapper) return;

        if (isMaster) {
            treeWrapper.classList.add('is-master-active');
            treeWrapper.querySelectorAll('input[data-perm-check="true"]').forEach((cb) => {
                cb.disabled = true;
            });
        } else {
            treeWrapper.classList.remove('is-master-active');
            treeWrapper.querySelectorAll('input[data-perm-check="true"]').forEach((cb) => {
                cb.disabled = false;
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

    /**
     * Verarbeitet die clevere Parent/Child Abhängigkeit (Das "TwoKinds-Modell")
     *
     * @param {HTMLInputElement} checkbox Die angeklickte Checkbox
     */
    handlePermissionChange(checkbox) {
        const node = checkbox.closest('.p-tree-node');

        if (checkbox.checked) {
            // FALL 1: KNOTEN WIRD AKTIVIERT

            // A) TOP-DOWN: Alle Kinder dieses Knotens zwingend mit aktivieren
            const childCbs = node.querySelectorAll('input[data-perm-check="true"]');
            childCbs.forEach((cb) => {
                if (!cb.checked && cb !== checkbox) {
                    cb.checked = true;
                    this.triggerHighlight(cb.closest('.p-item'), true);
                }
            });

            // B) BOTTOM-UP: Klettere den Baum hoch. Wenn alle Geschwister aktiv sind, aktiviere den Vater.
            let parent = node.parentElement.closest('.p-tree-node');
            while (parent) {
                const parentCb = parent.querySelector(
                    ':scope > .p-item input[data-perm-check="true"]'
                );
                if (parentCb) {
                    // Selektiere alle direkten Kinder dieses Vaters
                    const siblings = parent.querySelectorAll(
                        ':scope > .p-tree-node > .p-item input[data-perm-check="true"]'
                    );
                    const allChecked = Array.from(siblings).every((c) => c.checked);

                    if (allChecked && !parentCb.checked) {
                        parentCb.checked = true;
                        this.triggerHighlight(parentCb.closest('.p-item'), true);
                    }
                }
                parent = parent.parentElement.closest('.p-tree-node');
            }
        } else {
            // FALL 2: KNOTEN WIRD DEAKTIVIERT

            // A) TOP-DOWN: Alle Kinder dieses Knotens zwingend mit deaktivieren
            const childCbs = node.querySelectorAll('input[data-perm-check="true"]');
            childCbs.forEach((cb) => {
                if (cb.checked && cb !== checkbox) {
                    cb.checked = false;
                    this.triggerHighlight(cb.closest('.p-item'), false);
                }
            });

            // B) BOTTOM-UP: Klettere den Baum hoch und deaktiviere JEDEN Vater zwingend!
            // Denn wenn auch nur ein Kind fehlt, besitzt der Vater nicht mehr "alle" Rechte.
            let parent = node.parentElement.closest('.p-tree-node');
            while (parent) {
                const parentCb = parent.querySelector(
                    ':scope > .p-item input[data-perm-check="true"]'
                );
                if (parentCb && parentCb.checked) {
                    parentCb.checked = false;
                    this.triggerHighlight(parentCb.closest('.p-item'), false);
                }
                parent = parent.parentElement.closest('.p-tree-node');
            }
        }
    }

    async handleSystemUpdate(btn, zipUrl, csrfToken) {
        if (!zipUrl || !csrfToken) {
            alert('Fehler: Download-URL oder Sicherheits-Token fehlt.');
            return;
        }

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.style.opacity = '0.7';
        btn.style.cursor = 'wait';

        try {
            btn.innerText = 'Phase 1/2: Lade Update herunter...';
            const res1 = await fetch('api/perform_update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({ zip_url: zipUrl }),
            });
            const data1 = await res1.json();
            if (!data1.success)
                throw new Error(data1.error || 'Fehler in Phase 1 (Dateien kopieren).');

            btn.innerText = 'Phase 2/2: Aktualisiere Datenbank...';
            const res2 = await fetch('api/finalize_update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({}),
            });
            const data2 = await res2.json();
            if (!data2.success)
                throw new Error(data2.error || 'Fehler in Phase 2 (Datenbank Migration).');

            btn.innerText = 'Update erfolgreich!';
            btn.style.background = 'var(--success-color, #10b981)';
            alert(data2.message || 'Das System wurde erfolgreich aktualisiert.');
            window.location.reload();
        } catch (error) {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
            btn.innerHTML = originalText;
            alert('Update fehlgeschlagen:\n' + error.message);
        }
    }

    switchTab(tabId, activeBtn) {
        if (!tabId || !activeBtn) return;

        this.contents.forEach((c) => {
            c.classList.remove('c-tabs__content--active');
        });

        this.tabs.forEach((b) => {
            b.classList.remove('c-tabs__btn--active');
        });

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
