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

        // 4. Delegierte Klicks (Sperren & Update)
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

        // 5. Rechte-Matrix initialisieren
        this.initPermissionMatrix();
    }

    /**
     * Initialisiert die Logik für die Rollen-Matrix (Parent/Child-Abhängigkeiten)
     */
    initPermissionMatrix() {
        // UI-Modus ("hide" oder "grey") global registrieren und anwenden
        window.updateUiMode = (mode) => {
            document.querySelectorAll('.p-tree-wrapper').forEach((wrapper) => {
                wrapper.classList.remove('mode-hide', 'mode-grey');
                wrapper.classList.add('mode-' + mode);
            });
            localStorage.setItem('pref_perm_ui_mode', mode);
        };

        const savedMode = localStorage.getItem('pref_perm_ui_mode') || 'hide';
        window.updateUiMode(savedMode);

        const radio = document.querySelector(`input[name="ui_mode_toggle"][value="${savedMode}"]`);
        if (radio) radio.checked = true;

        // Initiale Berechnung der aktiven Pfade & Master-Toggle
        document.querySelectorAll('.permission-container').forEach((container) => {
            const wrapper = container.querySelector('.p-tree-wrapper');
            const masterCb = container.querySelector('[data-master-toggle="true"]');

            if (wrapper) this.updateTreePaths(wrapper);
            if (masterCb) this.applyMasterState(container, masterCb.checked);
        });

        // Event Delegation für Checkboxen
        document.addEventListener('change', (e) => {
            if (e.target.matches('[data-perm-check="true"]')) {
                this.handlePermissionChange(e.target);
            } else if (e.target.matches('[data-master-toggle="true"]')) {
                const container = e.target.closest('.permission-container');
                if (container) this.applyMasterState(container, e.target.checked);
            }
        });
    }

    /**
     * Gott-Modus (*): Graut den Baum aus und sperrt die Bedienung
     */
    applyMasterState(container, isMaster) {
        const treeWrapper = container.querySelector('.p-tree-wrapper');
        if (!treeWrapper) return;

        if (isMaster) {
            treeWrapper.classList.add('is-master-active');
            treeWrapper.querySelectorAll('input[data-perm-check="true"]').forEach((cb) => {
                cb.disabled = true;
                cb.parentElement.style.opacity = '0.5';
                cb.parentElement.style.pointerEvents = 'none';
            });
        } else {
            treeWrapper.classList.remove('is-master-active');
            treeWrapper.querySelectorAll('input[data-perm-check="true"]').forEach((cb) => {
                cb.disabled = false;
                cb.parentElement.style.opacity = '1';
                cb.parentElement.style.pointerEvents = 'auto';
            });
        }
    }

    /**
     * Hilfsmethode für kurzes visuelles Feedback
     */
    triggerHighlight(element) {
        if (!element) return;
        element.classList.add('is-auto-active');
        setTimeout(() => element.classList.remove('is-auto-active'), 800);
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
        const container = checkbox.closest('.permission-container');
        const wrapper = container.querySelector('.p-tree-wrapper');

        // UX-Rescue: Wenn ein User im "Fokus"-Modus (hide) etwas abwählt, wechseln wir auf
        // "Experte" (grey), damit der abgewählte Baum nicht sofort komplett verschwindet.
        if (wrapper && wrapper.classList.contains('mode-hide') && !checkbox.checked) {
            const radioGrey = document.querySelector('input[name="ui_mode_toggle"][value="grey"]');
            if (radioGrey) {
                radioGrey.checked = true;
                window.updateUiMode('grey');
            }
        }

        if (checkbox.checked) {
            // BOTTOM-UP: Kind aktiviert -> alle Väter an
            let parent = node.parentElement.closest('.p-tree-node');
            while (parent) {
                const parentCb = parent.firstElementChild.querySelector('[data-perm-check="true"]');
                if (parentCb && !parentCb.checked) {
                    parentCb.checked = true;
                    this.triggerHighlight(parentCb.closest('.p-item'));
                }
                parent = parent.parentElement.closest('.p-tree-node');
            }

            // TOP-DOWN (Zusatz für TwoKinds-Verhalten): Vater aktiviert -> alle Kinder zwingend an
            const childCbs = node.querySelectorAll('.p-tree-node input[data-perm-check="true"]');
            childCbs.forEach((cb) => {
                if (!cb.checked) {
                    cb.checked = true;
                    this.triggerHighlight(cb.closest('.p-item'));
                }
            });
        } else {
            // TOP-DOWN: Vater deaktiviert -> alle tiefen Kinder aus
            const childCbs = node.querySelectorAll('.p-tree-node [data-perm-check="true"]');
            childCbs.forEach((cb) => (cb.checked = false));
        }

        // Visuelles Update für "Gesperrt"-Status und Sichtbarkeiten
        if (container) this.refreshTreeVisually(container);
        if (wrapper) this.updateTreePaths(wrapper);
    }

    /**
     * Evaluiert, welche Knoten gesperrt (is-locked) sind, weil der Vater fehlt.
     */
    refreshTreeVisually(container) {
        const nodes = container.querySelectorAll('.p-tree-node');
        nodes.forEach((node) => {
            const parentNode = node.parentElement.closest('.p-tree-node');
            if (parentNode) {
                const parentCheckbox = parentNode.querySelector(
                    ':scope > .p-item input[data-perm-check="true"]'
                );
                if (parentCheckbox && !parentCheckbox.checked) {
                    node.classList.add('is-locked');
                } else {
                    node.classList.remove('is-locked');
                }
            }
        });
    }

    /**
     * Fügt aktiven Pfaden die Klasse '.is-active-path' hinzu (für den Fokus-Modus)
     *
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
     * Steuert den manuellen 2-Phasen Update-Prozess (falls nicht via GitHub Actions)
     *
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
