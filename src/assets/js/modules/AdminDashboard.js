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
        this.searchInput = this.container.querySelector('.js-admin-search');

        // Zentraler Zerstörer für alle delegierten Events
        this.abortController = new AbortController();

        this.init();
        this.restoreLastTab();
        this.handleUrlParams();
        this.initFinanceBulk();
    }

    init() {
        const options = { signal: this.abortController.signal };

        // Datums-Filter Logik
        const filterStart = this.container.querySelector('.js-filter-start');
        const filterEnd = this.container.querySelector('.js-filter-end');
        const filterForm = this.container.querySelector('.js-dashboard-filter-form');

        if (filterStart && filterEnd && filterForm) {
            filterStart.addEventListener(
                'change',
                () => {
                    if (
                        filterStart.value &&
                        filterEnd.value &&
                        filterStart.value > filterEnd.value
                    ) {
                        filterEnd.value = filterStart.value;
                    }
                    filterForm.submit();
                },
                options
            );

            filterEnd.addEventListener(
                'change',
                () => {
                    if (
                        filterStart.value &&
                        filterEnd.value &&
                        filterEnd.value < filterStart.value
                    ) {
                        filterStart.value = filterEnd.value;
                    }
                    filterForm.submit();
                },
                options
            );
        }

        // 1. Tab-Steuerung
        this.tabs.forEach((btn) => {
            btn.setAttribute('role', 'tab');
            btn.addEventListener(
                'click',
                (e) => {
                    e.preventDefault();
                    this.switchTab(btn.getAttribute('data-tab-target'), btn);
                },
                options
            );
        });

        // FIX: Explizite Blockklammern verhindern implizite Returns
        this.contents.forEach((content) => {
            content.setAttribute('role', 'tabpanel');
        });

        // 2. Server-Side Such-Logik (Debounce)
        if (this.searchInput) {
            const form = this.container.querySelector('.js-dashboard-filter-form');
            if (form) {
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

        // 3. Delegierte Klicks
        this.container.addEventListener(
            'click',
            async (e) => {
                const suspendBtn = e.target.closest('.js-suspend-btn');
                if (suspendBtn) {
                    e.preventDefault();
                    const code = suspendBtn.dataset.code;

                    const reason = await this.#promptReason(code);

                    if (reason && reason.trim() !== '') {
                        const form = this.container.querySelector(
                            `.js-form-suspend[data-code="${code}"]`
                        );
                        const input = form?.querySelector('.js-reason-suspend');
                        if (form && input) {
                            input.value = reason.trim();
                            form.submit();
                        }
                    }
                }
            },
            options
        );
    }

    /**
     * Erzeugt dynamisch einen barrierefreien HTML5-Dialog,
     * um den blockierenden I/O prompt() zu ersetzen.
     */
    async #promptReason(code) {
        return new Promise((resolve) => {
            const dialog = document.createElement('dialog');
            dialog.className = 'c-modal';
            dialog.innerHTML = `
                <div class="c-modal__dialog">
                    <h3 class="c-modal__title">Grund für die Sperre?</h3>
                    <p class="u-color-muted u-margin-block-start-s">Bitte geben Sie den Grund für die Sperrung von <strong class="u-color-main">${code}</strong> ein:</p>
                    <div class="c-form-group u-margin-block-start-m">
                        <input type="text" class="c-form-input js-prompt-input" required aria-label="Sperrgrund">
                    </div>
                    <div class="u-flex u-gap-s u-margin-block-start-m">
                        <button type="button" class="c-button c-button--secondary u-flex-grow-1 js-prompt-cancel">Abbrechen</button>
                        <button type="button" class="c-button c-button--danger u-flex-grow-1 js-prompt-confirm">Sperren</button>
                    </div>
                </div>
            `;
            document.body.appendChild(dialog);
            dialog.showModal();

            const input = dialog.querySelector('.js-prompt-input');
            const btnCancel = dialog.querySelector('.js-prompt-cancel');
            const btnConfirm = dialog.querySelector('.js-prompt-confirm');

            const cleanup = (value) => {
                dialog.close();
                dialog.remove();
                resolve(value);
            };

            btnCancel.addEventListener('click', () => cleanup(null));
            btnConfirm.addEventListener('click', () => cleanup(input.value));
            dialog.addEventListener('cancel', () => cleanup(null));
        });
    }

    initFinanceBulk() {
        this.bulkCheckboxes = this.container.querySelectorAll('.js-bulk-pay-cb');
        this.bulkToggleAll = this.container.querySelector('.js-bulk-pay-toggle-all');

        this.btnPay = this.container.querySelector('.js-bulk-pay-btn');
        this.btnRemind = this.container.querySelector('.js-bulk-remind-btn');
        this.countSpanPay = this.container.querySelector('.js-bulk-pay-count');
        this.countSpanRemind = this.container.querySelector('.js-bulk-remind-count');

        const options = { signal: this.abortController.signal };

        if (this.bulkToggleAll) {
            this.bulkToggleAll.addEventListener(
                'change',
                (e) => {
                    // FIX: Blockklammern
                    this.bulkCheckboxes.forEach((cb) => {
                        cb.checked = e.target.checked;
                    });
                    this.updateBulkPayButton();
                },
                options
            );
        }

        // FIX: Blockklammern
        this.bulkCheckboxes.forEach((cb) => {
            cb.addEventListener(
                'change',
                () => {
                    this.updateBulkPayButton();
                },
                options
            );
        });
    }

    updateBulkPayButton() {
        const checkedCount = Array.from(this.bulkCheckboxes).filter((cb) => cb.checked).length;
        if (this.btnPay && this.btnRemind) {
            if (this.countSpanPay) this.countSpanPay.innerText = checkedCount;
            if (this.countSpanRemind) this.countSpanRemind.innerText = checkedCount;

            const isHidden = checkedCount === 0;
            // Strikte Nutzung von nativen Properties statt verbotener .u-hidden Klassen
            this.btnPay.hidden = isHidden;
            this.btnRemind.hidden = isHidden;
        }
    }

    switchTab(tabId, activeBtn) {
        if (!tabId || !activeBtn) return;
        const target = document.getElementById(tabId); // Target-ID für Tabs ist i.O. (Anchor-Pattern)
        if (!target) return;

        // WAI-ARIA strikt anwenden
        this.contents.forEach((c) => {
            c.classList.remove('is-active');
            c.setAttribute('aria-hidden', 'true');
        });
        this.tabs.forEach((b) => {
            b.classList.remove('is-active');
            b.setAttribute('aria-selected', 'false');
            b.setAttribute('tabindex', '-1');
        });

        target.classList.add('is-active');
        target.setAttribute('aria-hidden', 'false');

        activeBtn.classList.add('is-active');
        activeBtn.setAttribute('aria-selected', 'true');
        activeBtn.removeAttribute('tabindex');

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

        if (targetBtn) this.switchTab(lastTab, targetBtn);
    }

    handleUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);

        // Audit Logs
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
        // Verhindert Phantom-Formular-Submits nach dem Verlassen der Seite
        if (this.debouncedSearch && typeof this.debouncedSearch.cancel === 'function') {
            this.debouncedSearch.cancel();
        }
        // Tötet alle delegierten Events sofort und restlos
        this.abortController.abort();
    }
}
