import { debounce } from '../utils/Utils.js';

/**
 * Controller für globale Admin-Dashboard Funktionen.
 * Strenges Memory-Management mit AbortController und Event-Delegation.
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

        // BATCHED DOM WRITE: Initiale Tab-A11y Rollen setzen
        requestAnimationFrame(() => {
            this.tabs.forEach((btn) => btn.setAttribute('role', 'tab'));
            this.contents.forEach((content) => content.setAttribute('role', 'tabpanel'));
        });

        // 1. Tab-Steuerung via EVENT DELEGATION
        const tabNav = this.container.querySelector('.c-tabs__nav');
        if (tabNav) {
            tabNav.addEventListener(
                'click',
                (e) => {
                    const btn = e.target.closest('[data-tab-target]');
                    if (btn) {
                        e.preventDefault();
                        this.switchTab(btn.getAttribute('data-tab-target'), btn, true);
                    }
                },
                options
            );
        }

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

        // 3. Delegierte Klicks (Manuelle Aktionen)
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

            const wrapper = document.createElement('div');
            wrapper.className = 'c-modal__dialog';

            const title = document.createElement('h3');
            title.className = 'c-modal__title';
            title.textContent = 'Grund für die Sperre?';

            const desc = document.createElement('p');
            desc.className = 'u-color-muted u-margin-block-start-s';
            desc.textContent = 'Bitte geben Sie den Grund für die Sperrung von ';

            const strong = document.createElement('strong');
            strong.className = 'u-color-main';
            strong.textContent = code;

            desc.appendChild(strong);
            desc.appendChild(document.createTextNode(' ein:'));

            const formGroup = document.createElement('div');
            formGroup.className = 'c-form-group u-margin-block-start-m';

            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'c-form-input js-prompt-input';
            input.required = true;
            input.setAttribute('aria-label', 'Sperrgrund');

            formGroup.appendChild(input);

            const btnGroup = document.createElement('div');
            btnGroup.className = 'u-flex u-gap-s u-margin-block-start-m';

            const btnCancel = document.createElement('button');
            btnCancel.type = 'button';
            btnCancel.className = 'c-button c-button--secondary u-flex-grow-1 js-prompt-cancel';
            btnCancel.textContent = 'Abbrechen';

            const btnConfirm = document.createElement('button');
            btnConfirm.type = 'button';
            btnConfirm.className = 'c-button c-button--danger u-flex-grow-1 js-prompt-confirm';
            btnConfirm.textContent = 'Sperren';

            btnGroup.append(btnCancel, btnConfirm);
            wrapper.append(title, desc, formGroup, btnGroup);
            dialog.appendChild(wrapper);
            document.body.appendChild(dialog);

            dialog.showModal();

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
                    this.bulkCheckboxes.forEach((cb) => {
                        cb.checked = e.target.checked;
                    });
                    this.updateBulkPayButton();
                },
                options
            );
        }

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
            // DOM Writes batchen
            requestAnimationFrame(() => {
                if (this.countSpanPay) this.countSpanPay.textContent = checkedCount;
                if (this.countSpanRemind) this.countSpanRemind.textContent = checkedCount;

                const isHidden = checkedCount === 0;
                this.btnPay.hidden = isHidden;
                this.btnRemind.hidden = isHidden;
            });
        }
    }

    switchTab(tabId, activeBtn, isUserClick = false) {
        if (!tabId || !activeBtn) return;

        // Scoped Query: Verhindert Konflikte bei mehreren Instanzen
        const target = this.container.querySelector(`#${tabId}`);
        if (!target) return;

        // BATCHED DOM WRITE: Layout Thrashing verhindern
        requestAnimationFrame(() => {
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
        });

        try {
            localStorage.setItem('lastAdminTab', tabId);
        } catch {
            // Ignore blockierte Storage
        }

        // ARCHITEKTUR-FIX: Wenn der Nutzer klickt, bereinigen wir die URL von alten Paginierungs- & Focus-Parametern
        if (isUserClick && window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            let changed = false;

            ['focus', 'page', 'audit_page'].forEach((param) => {
                if (url.searchParams.has(param)) {
                    url.searchParams.delete(param);
                    changed = true;
                }
            });

            if (changed) {
                // Wenn search leer ist, setzen wir nur den Pfadname, ansonsten inkl. Rest-Parametern (z.B. Start/End Filter)
                const newUrl = url.search ? url.toString() : url.pathname;
                window.history.replaceState({}, '', newUrl);
            }
        }
    }

    restoreLastTab() {
        let lastTab = 'tab-active';
        try {
            lastTab = localStorage.getItem('lastAdminTab') || 'tab-active';
        } catch {
            // Ignore
        }

        // ARCHITEKTUR-FIX: Wenn PHP bereits einen Tab via URL focus= erzwingt,
        // darf das JS/LocalStorage diesen NICHT mehr überschreiben!
        const urlParams = new URLSearchParams(window.location.search);
        if (
            urlParams.has('focus') ||
            urlParams.has('audit_filter') ||
            document.querySelector('.js-bank-preview-data')
        ) {
            return; // PHP hat die Kontrolle übernommen
        }

        let targetBtn = null;
        try {
            targetBtn = this.container.querySelector(`[data-tab-target="${lastTab}"]`);
        } catch {
            targetBtn = this.container.querySelector('[data-tab-target="tab-active"]');
        }

        // Beim automatischen Laden KEIN isUserClick Flag mitgeben, damit Parameter überleben
        if (targetBtn) this.switchTab(lastTab, targetBtn);
    }

    handleUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);

        // Audit Logs
        if (urlParams.has('audit_filter')) {
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
