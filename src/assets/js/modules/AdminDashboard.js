import { debounce } from '../utils/Utils.js';

/**
 * Controller für globale Admin-Dashboard Funktionen.
 * Strenges Memory-Management mit AbortController für Event-Delegation.
 */
export class AdminDashboard {
    constructor(container) {
        this.container = container;
        this.tabs = this.container.querySelectorAll('[data-tab-target]');
        this.contents = this.container.querySelectorAll('.c-tabs__content');
        this.searchInput = document.getElementById('adminSearch');

        // Zentraler Zerstörer für alle delegierten Events
        this.abortController = new AbortController();

        this.init();
        this.restoreLastTab();
        this.handleUrlParams();
        this.initFinanceBulk();
    }

    init() {
        const options = { signal: this.abortController.signal };

        // 1. Tab-Steuerung
        this.tabs.forEach((btn) => {
            btn.addEventListener(
                'click',
                (e) => {
                    e.preventDefault();
                    this.switchTab(btn.getAttribute('data-tab-target'), btn);
                },
                options
            );
        });

        // 2. Server-Side Such-Logik (Debounce)
        if (this.searchInput) {
            const form = document.getElementById('dashboardFilterForm');
            if (form) {
                // Die referenzierte Handler-Funktion muss im Speicher bleiben
                this.debouncedSearch = debounce(() => form.submit(), 600);
                this.searchInput.addEventListener('input', this.debouncedSearch, options);
            }

            if (this.searchInput.value) {
                const val = this.searchInput.value;
                this.searchInput.value = '';
                this.searchInput.value = val;
                this.searchInput.focus();
            }
        }

        // 3. Delegierte Klicks für "Sperren" Buttons
        // Delegiertes Event an AbortSignal binden!
        this.container.addEventListener(
            'click',
            (e) => {
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
            },
            options
        );
    }

    initFinanceBulk() {
        this.bulkCheckboxes = this.container.querySelectorAll('.js-bulk-pay-cb');
        this.bulkToggleAll = this.container.querySelector('.js-bulk-pay-toggle-all');
        this.btnPay = document.getElementById('bulkPayBtn');
        this.btnRemind = document.getElementById('bulkRemindBtn');
        this.countSpanPay = document.getElementById('bulkPayCount');
        this.countSpanRemind = document.getElementById('bulkRemindCount');

        const options = { signal: this.abortController.signal };

        if (this.bulkToggleAll) {
            this.bulkToggleAll.addEventListener(
                'change',
                (e) => {
                    this.bulkCheckboxes.forEach((cb) => {
                        cb.checked = e.target.checked;
                    });
                    this.updateBulkPayButton();
                },
                options
            );
        }

        this.bulkCheckboxes.forEach((cb) => {
            cb.addEventListener('change', () => this.updateBulkPayButton(), options);
        });
    }

    updateBulkPayButton() {
        const checkedCount = Array.from(this.bulkCheckboxes).filter((cb) => cb.checked).length;
        if (this.btnPay && this.btnRemind) {
            if (this.countSpanPay) this.countSpanPay.innerText = checkedCount;
            if (this.countSpanRemind) this.countSpanRemind.innerText = checkedCount;

            const isHidden = checkedCount === 0;
            this.btnPay.classList.toggle('u-hidden', isHidden);
            this.btnRemind.classList.toggle('u-hidden', isHidden);
        }
    }

    switchTab(tabId, activeBtn) {
        if (!tabId || !activeBtn) return;
        const target = document.getElementById(tabId);
        if (!target) return;

        this.contents.forEach((c) => c.classList.remove('c-tabs__content--active'));
        this.tabs.forEach((b) => b.classList.remove('c-tabs__btn--active'));

        target.classList.add('c-tabs__content--active');
        activeBtn.classList.add('c-tabs__btn--active');

        try {
            localStorage.setItem('lastAdminTab', tabId);
        } catch {
            // Ignore blockierte Storage
        }
    }

    restoreLastTab() {
        let lastTab = 'tab-active';
        try {
            lastTab = localStorage.getItem('lastAdminTab') || 'tab-active';
        } catch {
            // Ignore
        }

        // Schutz vor manipulierten Storage-Strings, die den Sektorenbau (DOMException) zum Absturz bringen!
        let targetBtn = null;
        try {
            targetBtn = document.querySelector(`[data-tab-target="${lastTab}"]`);
        } catch {
            targetBtn = document.querySelector('[data-tab-target="tab-active"]');
        }

        if (targetBtn) {
            this.switchTab(lastTab, targetBtn);
        }
    }

    handleUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);

        // Audit Logs (Alte Logik)
        if (urlParams.has('audit_page') || urlParams.has('audit_filter')) {
            const auditBtn = this.container.querySelector('[data-tab-target="tab-audit-log"]');
            if (auditBtn) this.switchTab('tab-audit-log', auditBtn);
        }

        // Globale Focus-Steuerung für Tabs
        // Zwingt das UI, einen bestimmten Tab zu öffnen (überschreibt den LocalStorage)
        const focusId = urlParams.get('focus');
        if (focusId?.startsWith('tab-')) {
            const btn = this.container.querySelector(`[data-tab-target="${focusId}"]`);
            if (btn) this.switchTab(focusId, btn);
        }
    }

    destroy() {
        // Tötet alle delegierten Events sofort und restlos
        this.abortController.abort();
    }
}
