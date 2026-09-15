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
        this.targetId = this.button.dataset.target;
        this.abortController = new AbortController();
        this.init();
    }
    init() {
        this.button.addEventListener(
            'click',
            (e) => {
                e.preventDefault();
                const form = document.getElementById(this.targetId);
                if (form) {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
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
        this.targetId = this.button.dataset.target;
        this.abortController = new AbortController();
        this.init();
    }
    init() {
        this.button.addEventListener(
            'click',
            (e) => {
                e.preventDefault();
                const target = document.getElementById(this.targetId);
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
