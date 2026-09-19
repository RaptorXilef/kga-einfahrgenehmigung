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

                // Speichere den Originalinhalt DOM-sicher (ohne HTML Strings!)
                const originalChildren = document.createDocumentFragment();
                while (this.button.firstChild) {
                    originalChildren.appendChild(this.button.firstChild);
                }

                const successAction = () => {
                    // Keine harten SCSS-Utility-Klassen im JS!
                    this.button.classList.add('is-success');
                    this.button.textContent = '✓ Kopiert';

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
                        await navigator.clipboard.writeText(this.url);
                        successAction();
                    } catch {
                        this.fallbackCopyText(this.url, successAction);
                    }
                } else {
                    this.fallbackCopyText(this.url, successAction);
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
