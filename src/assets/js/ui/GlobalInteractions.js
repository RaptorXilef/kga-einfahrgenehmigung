import { notifier } from '../core/Notifier.js';

/**
 * Sammlung winziger, hochspezifischer UI-Klassen (Single Responsibility Principle).
 * Kapselt globale Interaktionen, die zuvor monolithisch in der app.js lagen.
 */

export class ConfirmSubmit {
    constructor(form) {
        this.form = form;
        this.message = this.form.dataset.confirm || 'Sind Sie sicher?';
        this.abortController = new AbortController();
        this.init();
    }
    init() {
        this.form.addEventListener(
            'submit',
            (e) => {
                if (!window.confirm(this.message)) {
                    e.preventDefault();
                }
            },
            { signal: this.abortController.signal }
        );
    }
    destroy() {
        this.abortController.abort();
    }
}

export class ConfirmClick {
    constructor(button) {
        this.button = button;
        this.message = this.button.dataset.confirmClick || 'Sind Sie sicher?';
        this.abortController = new AbortController();
        this.init();
    }
    init() {
        this.button.addEventListener(
            'click',
            (e) => {
                if (!window.confirm(this.message)) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                }
            },
            { signal: this.abortController.signal }
        );
    }
    destroy() {
        this.abortController.abort();
    }
}

export class RemoteSubmit {
    constructor(button) {
        this.button = button;
        // Erwartet nun einen echten Selektor im HTML: data-target=".js-mein-formular"
        this.targetSelector = this.button.dataset.target;
        this.abortController = new AbortController();
        this.init();
    }
    init() {
        this.button.addEventListener(
            'click',
            (e) => {
                e.preventDefault();
                const form = document.querySelector(this.targetSelector);
                if (form) {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                } else {
                    console.warn(`[RemoteSubmit] Formular ${this.targetSelector} nicht gefunden.`);
                }
            },
            { signal: this.abortController.signal }
        );
    }
    destroy() {
        this.abortController.abort();
    }
}

export class TriggerClick {
    constructor(button) {
        this.button = button;
        // Erwartet nun einen echten Selektor im HTML: data-target=".js-mein-button"
        this.targetSelector = this.button.dataset.target;
        this.abortController = new AbortController();
        this.init();
    }
    init() {
        this.button.addEventListener(
            'click',
            (e) => {
                e.preventDefault();
                const target = document.querySelector(this.targetSelector);
                if (target) target.click();
            },
            { signal: this.abortController.signal }
        );
    }
    destroy() {
        this.abortController.abort();
    }
}

export class SelectOnClick {
    constructor(element) {
        this.element = element;
        this.abortController = new AbortController();
        this.init();
    }
    init() {
        this.element.addEventListener(
            'click',
            () => {
                this.element.select();
            },
            { signal: this.abortController.signal }
        );
    }
    destroy() {
        this.abortController.abort();
    }
}

export class AccordionCard {
    constructor(card) {
        this.card = card;
        this.toggleBtn = this.card.querySelector('.js-toggle-parent');
        this.abortController = new AbortController();
        if (this.toggleBtn) this.init();
    }

    init() {
        const isClosed = this.card.classList.contains('is-closed');
        const options = { signal: this.abortController.signal };

        if (this.toggleBtn.tagName !== 'BUTTON') {
            this.toggleBtn.setAttribute('role', 'button');
            this.toggleBtn.setAttribute('tabindex', '0');
        }
        this.toggleBtn.setAttribute('aria-expanded', !isClosed);

        const toggleAction = (e) => {
            e.preventDefault();
            const willClose = !this.card.classList.contains('is-closed');
            this.card.classList.toggle('is-closed');
            this.toggleBtn.setAttribute('aria-expanded', !willClose);
        };

        this.toggleBtn.addEventListener('click', toggleAction, options);
        this.toggleBtn.addEventListener(
            'keydown',
            (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault(); // FIX: Verhindert das Scrollen bei Space
                    toggleAction(e);
                }
            },
            options
        );
    }
    destroy() {
        this.abortController.abort();
    }
}

export class FabRefresh {
    constructor(button) {
        this.button = button;
        this.abortController = new AbortController();
        this.init();
    }
    init() {
        this.button.addEventListener(
            'click',
            (e) => {
                e.preventDefault();
                window.location.href =
                    window.location.origin + window.location.pathname + window.location.search;
            },
            { signal: this.abortController.signal }
        );
    }
    destroy() {
        this.abortController.abort();
    }
}

export class PrintControls {
    constructor(button) {
        this.button = button;
        this.isClose = this.button.classList.contains('js-close-window');
        this.abortController = new AbortController();
        this.init();
    }
    init() {
        this.button.addEventListener(
            'click',
            (e) => {
                e.preventDefault();
                if (this.isClose) {
                    window.close();
                } else {
                    window.print();
                }
            },
            { signal: this.abortController.signal }
        );
    }
    destroy() {
        this.abortController.abort();
    }
}

export class AutoSubmitSelect {
    constructor(select) {
        this.select = select;
        this.form = this.select.closest('form');
        this.abortController = new AbortController();
        if (this.form) this.init();
    }
    init() {
        this.select.addEventListener(
            'change',
            () => {
                if (typeof this.form.requestSubmit === 'function') {
                    this.form.requestSubmit();
                } else {
                    this.form.submit();
                }
            },
            { signal: this.abortController.signal }
        );
    }
    destroy() {
        this.abortController.abort();
    }
}

export class EventTracker {
    constructor(element) {
        this.element = element;
        this.eventName = this.element.dataset.event;
        this.abortController = new AbortController();
        this.init();
    }
    init() {
        // Führt Initialisierungen aus, Events sind hier nicht nötig
        if (this.eventName && typeof window.dataLayer !== 'undefined') {
            window.dataLayer.push({ event: this.eventName });
        }
    }
    destroy() {
        this.abortController.abort();
    }
}

export class CopyAction {
    constructor(button) {
        this.button = button;
        this.url = this.button.dataset.url;
        this.abortController = new AbortController();
        this.init();
    }

    init() {
        this.button.addEventListener(
            'click',
            async (e) => {
                e.preventDefault();
                // Lock-State verhindert Spam-Klicks
                if (this.button.dataset.isCopying) return;
                this.button.dataset.isCopying = 'true';

                // Unterstützt auch dynamisch aktualisierte data-url Attribute (z.B. im Payment-Modal)
                const textToCopy = this.button.dataset.url || this.url || '';

                // Speichere den Originalinhalt DOM-sicher (ohne HTML Strings!)
                const originalChildren = document.createDocumentFragment();
                while (this.button.firstChild) {
                    originalChildren.appendChild(this.button.firstChild);
                }

                const successAction = () => {
                    // Keine harten SCSS-Utility-Klassen im JS!
                    this.button.classList.add('is-success');
                    this.button.textContent = '✓';

                    notifier.show('In die Zwischenablage kopiert!', 'success');

                    setTimeout(() => {
                        this.button.classList.remove('is-success');
                        this.button.textContent = '';
                        this.button.appendChild(originalChildren);
                        delete this.button.dataset.isCopying;
                    }, 2000);
                };

                // Moderne Clipboard API mit Fallback
                if (navigator.clipboard && window.isSecureContext) {
                    try {
                        await navigator.clipboard.writeText(textToCopy);
                        successAction();
                    } catch {
                        this.fallbackCopyText(textToCopy, successAction);
                    }
                } else {
                    this.fallbackCopyText(textToCopy, successAction);
                }
            },
            { signal: this.abortController.signal }
        );
    }

    fallbackCopyText(text, callback) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.className = 'u-visually-hidden';
        document.body.appendChild(textArea);
        textArea.select();
        try {
            if (document.execCommand('copy')) callback();
            else delete this.button.dataset.isCopying;
        } catch {
            delete this.button.dataset.isCopying;
        }
        document.body.removeChild(textArea);
    }

    destroy() {
        this.abortController.abort();
    }
}

/**
 * Steuert das Zahlungs-Modal im Pächter-Verlauf (history_list.phtml)
 * und lädt den GiroCode-QR-Code ressourcenschonend erst bei Klick nach.
 */
export class PaymentInfoModal {
    constructor(modalElement) {
        this.modal = modalElement;
        this.codeEl = this.modal.querySelector('.js-pay-modal-code');
        this.amountEl = this.modal.querySelector('.js-pay-modal-amount');
        this.dueEl = this.modal.querySelector('.js-pay-modal-due');
        this.usageEl = this.modal.querySelector('.js-pay-modal-usage');
        this.copyUsageBtn = this.modal.querySelector('.js-pay-modal-copy-usage');
        this.qrBox = this.modal.querySelector('.js-pay-modal-qr-box');
        this.loaderEl = this.modal.querySelector('.js-pay-modal-loader');
        this.closeBtns = this.modal.querySelectorAll('.js-close-payment-modal');
        this.triggerBtns = document.querySelectorAll('.js-show-payment-info');

        this.abortController = new AbortController();
        this.init();
    }

    init() {
        const options = { signal: this.abortController.signal };

        this.triggerBtns.forEach((btn) => {
            btn.addEventListener(
                'click',
                (e) => {
                    e.preventDefault();
                    this.open(btn.dataset);
                },
                options
            );
        });

        this.closeBtns.forEach((btn) => {
            btn.addEventListener(
                'click',
                (e) => {
                    e.preventDefault();
                    this.close();
                },
                options
            );
        });

        this.modal.addEventListener(
            'click',
            (e) => {
                if (e.target === this.modal) {
                    this.close();
                }
            },
            options
        );
    }

    open(data) {
        if (this.codeEl) this.codeEl.textContent = data.code || '---';
        if (this.amountEl) this.amountEl.textContent = data.amount || '---';
        if (this.dueEl) this.dueEl.textContent = data.dueDate || '---';
        if (this.usageEl) this.usageEl.textContent = data.usage || '---';
        if (this.copyUsageBtn) this.copyUsageBtn.dataset.url = data.usage || '';

        if (this.qrBox && this.loaderEl) {
            const oldImg = this.qrBox.querySelector('img');
            if (oldImg) oldImg.remove();

            this.loaderEl.hidden = false;
            this.loaderEl.classList.remove('is-error');
            this.loaderEl.textContent = 'GiroCode wird geladen...';

            if (data.qrUrl) {
                const img = document.createElement('img');
                img.className = 'c-qr-box';
                img.alt = 'GiroCode für Banking-App';
                img.hidden = true;

                img.onload = () => {
                    if (this.abortController.signal.aborted) return;
                    this.loaderEl.hidden = true;
                    img.hidden = false;
                };

                img.onerror = () => {
                    if (this.abortController.signal.aborted) return;
                    img.hidden = true;
                    this.loaderEl.hidden = false;
                    this.loaderEl.textContent = 'QR-Code konnte nicht geladen werden.';
                    this.loaderEl.classList.add('is-error');
                };

                img.src = data.qrUrl;
                this.qrBox.appendChild(img);
            }
        }

        this.modal.showModal();
    }

    close() {
        this.modal.close();
        if (this.qrBox) {
            const oldImg = this.qrBox.querySelector('img');
            if (oldImg) oldImg.remove();
        }
    }

    destroy() {
        this.abortController.abort();
    }
}
