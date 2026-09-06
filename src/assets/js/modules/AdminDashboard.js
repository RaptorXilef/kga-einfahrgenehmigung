import { debounce } from '../utils/Utils.js';

/**
 * Controller für globale Admin-Dashboard Funktionen:
 * Tab-Navigation (inkl. Audit-Log Redirect), Such-Debounce,
 * die Genehmigungs-Sperre (Prompt) und Finance Bulk-Aktionen.
 */
export class AdminDashboard {
    constructor(container) {
        this.container = container;
        this.tabs = this.container.querySelectorAll('[data-tab-target]');
        this.contents = this.container.querySelectorAll('.c-tabs__content');
        this.searchInput = document.getElementById('adminSearch');

        this.init();
        this.restoreLastTab();
        this.handleUrlParams();
        this.initFinanceBulk();
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
            const form = document.getElementById('dashboardFilterForm');
            if (form) {
                // Submit wird gedrosselt ausgelöst, nachdem der Nutzer aufgehört hat zu tippen
                this.searchInput.addEventListener(
                    'input',
                    debounce(() => form.submit(), 600)
                );
            }

            // Cursor ans Ende des Textfelds setzen, wenn es schon einen Wert hat
            if (this.searchInput.value) {
                const val = this.searchInput.value;
                this.searchInput.value = '';
                this.searchInput.value = val;
                this.searchInput.focus();
            }
        }

        // 3. Delegierte Klicks für "Sperren" Buttons
        this.container.addEventListener('click', (e) => {
            const suspendBtn = e.target.closest('.js-suspend-btn');
            if (suspendBtn) {
                e.preventDefault();
                const code = suspendBtn.dataset.code;

                // Architektonische Notiz: prompt() blockiert den Main-Thread.
                // Für diese kritische Admin-Aktion (Sperren) ist das beabsichtigt,
                // um weitere Interaktionen zu verhindern, bis der Admin entschieden hat.
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
    }

    // Behandelt die Checkbox-Logik im Finanz-Tab
    initFinanceBulk() {
        this.bulkCheckboxes = this.container.querySelectorAll('.js-bulk-pay-cb');
        this.bulkToggleAll = this.container.querySelector('.js-bulk-pay-toggle-all');
        this.btnPay = document.getElementById('bulkPayBtn');
        this.btnRemind = document.getElementById('bulkRemindBtn');
        this.countSpanPay = document.getElementById('bulkPayCount');
        this.countSpanRemind = document.getElementById('bulkRemindCount');

        if (this.bulkToggleAll) {
            this.bulkToggleAll.addEventListener('change', (e) => {
                this.bulkCheckboxes.forEach((cb) => {
                    cb.checked = e.target.checked;
                });
                this.updateBulkPayButton();
            });
        }

        this.bulkCheckboxes.forEach((cb) => {
            cb.addEventListener('change', () => this.updateBulkPayButton());
        });
    }

    updateBulkPayButton() {
        const checkedCount = Array.from(this.bulkCheckboxes).filter((cb) => cb.checked).length;
        if (this.btnPay && this.btnRemind) {
            if (this.countSpanPay) this.countSpanPay.innerText = checkedCount;
            if (this.countSpanRemind) this.countSpanRemind.innerText = checkedCount;

            const displayStyle = checkedCount > 0 ? 'inline-flex' : 'none';
            this.btnPay.style.display = displayStyle;
            this.btnRemind.style.display = displayStyle;
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
            // Absicherung gegen blockierten localStorage (SecurityError)
            try {
                localStorage.setItem('lastAdminTab', tabId);
            } catch {
                // Ignore
            }
        }
    }

    restoreLastTab() {
        let lastTab = 'tab-active';
        // Absicherung gegen blockierten localStorage
        try {
            lastTab = localStorage.getItem('lastAdminTab') || 'tab-active';
        } catch {
            // Ignore
        }

        const targetBtn = document.querySelector(`[data-tab-target="${lastTab}"]`);
        if (targetBtn) {
            this.switchTab(lastTab, targetBtn);
        }
    }

    handleUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('audit_page') || urlParams.has('audit_filter')) {
            const auditBtn = this.container.querySelector('[data-tab-target="tab-audit-log"]');
            if (auditBtn) this.switchTab('tab-audit-log', auditBtn);
        }
    }
}
