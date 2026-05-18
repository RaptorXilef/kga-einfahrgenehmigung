// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
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

    init() {
        // 1. Tabs initialisieren
        this.tabs.forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchTab(btn.getAttribute('data-tab-target'), btn);
            });
        });

        // 2. Suche initialisieren
        if (this.searchInput) {
            this.searchInput.addEventListener('input', (e) => this.filterTables(e.target.value));
        }

        // 3. Spezial-Zeitraum Umschaltung
        if (this.templateSelect) {
            this.templateSelect.addEventListener('change', (e) => {
                const wrapper = document.getElementById('custom_end_wrapper');
                if (wrapper) {
                    wrapper.style.display = e.target.value.includes('custom') ? 'block' : 'none';
                }
            });
        }

        // 4. Sperr-Buttons (Event Delegation - Sicher für AJAX/DOM-Wechsel)
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

    filterTables(searchTerm) {
        const query = searchTerm.toLowerCase().trim();
        document.querySelectorAll('.c-table tbody tr').forEach((row) => {
            row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
        });
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
