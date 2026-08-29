/**
 * UI-Handler für das administrative Dashboard.
 *
 * Steuert das Client-seitige Tab-Switching inklusive Zustandsspeicherung (localStorage),
 * die Echtzeit-Tabellenfilterung bei Suchen, dynamische Formular-Sichtbarkeiten,
 * den administrativen Workflow für Genehmigungssperren über Prompts sowie
 * den 2-Phasen System-Update-Prozess.
 *
 * Kontext: Kernkomponente für die Interaktivität des Admin-Backends.
 *
 * Path: src/assets/js/admin-handler.js
 * Path: public/assets/js/admin-handler.min.js
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

    /**
     * Initialisiert die Event-Listener für das Dashboard.
     * Bindet Klick-Events für Tabs, Input-Events für die Filterung, Change-Events für Custom-Tarife
     * und fängt Delegated Clicks für den Sperren-Button sowie den Update-Button ab.
     *
     * @return {void}
     */
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
                }, 600); // Sendet das Formular 0.6 Sek nach dem letzten Tastendruck
            });

            // Cursor nach dem Neuladen ans Ende des Textes setzen
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

        // 4. Sperr-Logik (Prompts) & 5. 2-Phasen Update-Logik
        document.addEventListener('click', (e) => {
            // A) Logik für Sperr-Buttons
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

            // B) Logik für das System-Update
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

        this.initPermissionMatrix();
    }

    /**
     * Initialisiert die Logik für die Rollen-Matrix (Parent/Child-Abhängigkeiten)
     * und das UI-Toggling.
     */
    initPermissionMatrix() {
        // Initiale Berechnung der aktiven Pfade für alle existierenden Bäume
        document.querySelectorAll('.p-tree-wrapper').forEach((wrapper) => {
            this.updateTreePaths(wrapper);
        });

        // Event Delegation für Checkboxen in der Matrix
        document.addEventListener('change', (e) => {
            if (e.target.matches('[data-perm-check="true"]')) {
                this.handlePermissionChange(e.target);
            } else if (e.target.matches('[data-master-toggle="true"]')) {
                const wrapper = e.target.closest('form').querySelector('.p-tree-wrapper');
                if (wrapper) {
                    wrapper.classList.toggle('is-master-active', e.target.checked);
                }
            }
        });

        // Globale Funktion für den UI Toggle (wird inline via onchange aus Radiobuttons aufgerufen)
        window.updateUiMode = (mode) => {
            document.querySelectorAll('.p-tree-wrapper').forEach((wrapper) => {
                wrapper.classList.remove('mode-hide', 'mode-grey');
                wrapper.classList.add('mode-' + mode);
            });
        };
    }

    /**
     * Verarbeitet Abhängigkeiten, wenn eine Berechtigung angeklickt wird.
     * Kind aktiviert -> Eltern müssen zwingend aktiviert werden.
     * Elternteil deaktiviert -> Kinder müssen zwingend deaktiviert werden.
     *
     * @param {HTMLInputElement} checkbox Die angeklickte Berechtigungs-Checkbox
     */
    handlePermissionChange(checkbox) {
        const node = checkbox.closest('.p-tree-node');
        const wrapper = checkbox.closest('.p-tree-wrapper');

        if (checkbox.checked) {
            // Wenn ein Kind aktiviert wird -> klettere den Baum hoch und aktiviere alle Eltern
            let parent = node.parentElement.closest('.p-tree-node');
            while (parent) {
                const parentCb = parent.firstElementChild.querySelector('[data-perm-check="true"]');
                if (parentCb && !parentCb.checked) {
                    parentCb.checked = true;
                }
                parent = parent.parentElement.closest('.p-tree-node');
            }
        } else {
            // Wenn ein Elternteil deaktiviert wird -> alle tiefen Kinder zwingend deaktivieren
            const childCbs = node.querySelectorAll('.p-tree-node [data-perm-check="true"]');
            childCbs.forEach((cb) => (cb.checked = false));
        }

        // Pfade für den UI-Modus ("Fokus" / "Experte") nach der Änderung neu berechnen
        this.updateTreePaths(wrapper);
    }

    /**
     * Identifiziert alle aktiven Knoten und deren Eltern, um sie für den Fokus-Modus
     * sichtbar zu schalten. Verleiht den Elementen die Klasse `.is-active-path`.
     *
     * @param {HTMLElement} wrapper Der Container des Rechtes-Baums
     */
    updateTreePaths(wrapper) {
        // Zunächst alle Resetten
        wrapper
            .querySelectorAll('.p-tree-node')
            .forEach((n) => n.classList.remove('is-active-path'));

        // Alle momentan gecheckten Checkboxen holen
        const checkedCbs = wrapper.querySelectorAll('[data-perm-check="true"]:checked');
        checkedCbs.forEach((cb) => {
            let curr = cb.closest('.p-tree-node');
            while (curr) {
                curr.classList.add('is-active-path');
                curr = curr.parentElement.closest('.p-tree-node');
            }
        });
    }

    /**
     * Steuert den 2-Phasen Update-Prozess via AJAX Fetch.
     * Phase 1: ZIP herunterladen und Code entpacken.
     * Phase 2: RAM mit neuem Code laden und Datenbank-Migrationen durchführen.
     *
     * @param {HTMLElement} btn Der geklickte Update-Button
     * @param {string} zipUrl Die GitHub Release Download-URL
     * @param {string} csrfToken Das Sicherheits-Token der aktuellen Session
     */
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
            // --- PHASE 1: Download & Copy ---
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

            // --- PHASE 2: Migrate Database ---
            btn.innerText = 'Phase 2/2: Aktualisiere Datenbank...';
            const res2 = await fetch('api/finalize_update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({}), // Kein Body nötig, triggert nur die Migration
            });
            const data2 = await res2.json();
            if (!data2.success)
                throw new Error(data2.error || 'Fehler in Phase 2 (Datenbank Migration).');

            // --- ERFOLG ---
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

    /**
     * Schaltet die aktive Ansicht (Tab) um und persistiert die ID.
     *
     * @param {string}      tabId     Die ID des Ziel-Inhaltselements (z.B. 'tab-active').
     * @param {HTMLElement} activeBtn Der geklickte Tab-Button zur optischen Aktivierung.
     *
     * @return {void}
     */
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

    /**
     * Stellt den zuletzt geöffneten Tab nach einem Page-Reload wieder her.
     * Holt die ID aus dem localStorage (Fallback: 'tab-active') und triggert den Wechsel.
     *
     * @return {void}
     */
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
