/**
 * UI-Handler für das administrative Dashboard.
 *
 * Steuert das Client-seitige Tab-Switching inklusive Zustandsspeicherung (localStorage),
 * die Echtzeit-Tabellenfilterung bei Suchen, dynamische Formular-Sichtbarkeiten
 * sowie den administrativen Workflow für Genehmigungssperren über Prompts.
 * Kontext: Kernkomponente für die Interaktivität des Admin-Backends.
 *
 * Path: src/assets/js/admin-handler.js
 * Path: public/assets/js/admin-handler.min.js
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
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
     * und fängt Delegated Clicks für den Sperren-Button (`.js-suspend-btn`) ab.
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

        // 4. Sperr-Logik (Prompts)
        document.addEventListener('click', (e) => {
            // Prüfen, ob das geklickte Element (oder ein Elternteil davon) die Klasse hat
            const btn = e.target.closest('.js-suspend-btn');
            if (!btn) return;

            e.preventDefault();
            const code = btn.getAttribute('data-code');
            const reason = prompt(`Grund für die Sperre von ${code}?`);

            if (reason && reason.trim() !== '') {
                const form = document.getElementById(`form_suspend_${code}`);
                const input = document.getElementById(`reason_suspend_${code}`);
                if (form && input) {
                    input.value = reason;
                    form.submit();
                }
            }
        });
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
