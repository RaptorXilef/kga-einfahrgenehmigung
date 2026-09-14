/**
 * Sammlung winziger, hochspezifischer UI-Klassen (Single Responsibility Principle).
 * Kapselt globale Interaktionen, die zuvor monolithisch in der app.js lagen.
 */

export class ConfirmSubmit {
    constructor(form) {
        this.form = form;
        this.message = this.form.dataset.confirm || 'Sind Sie sicher?';
        this.init();
    }
    init() {
        this.form.addEventListener('submit', (e) => {
            if (!window.confirm(this.message)) {
                e.preventDefault();
            }
        });
    }
}

export class ConfirmClick {
    constructor(button) {
        this.button = button;
        this.message = this.button.dataset.confirmClick || 'Sind Sie sicher?';
        this.init();
    }
    init() {
        this.button.addEventListener('click', (e) => {
            if (!window.confirm(this.message)) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
        });
    }
}

export class RemoteSubmit {
    constructor(button) {
        this.button = button;
        this.targetId = this.button.dataset.target;
        this.init();
    }
    init() {
        this.button.addEventListener('click', (e) => {
            e.preventDefault();
            const form = document.getElementById(this.targetId);
            if (form) {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }
        });
    }
}

export class TriggerClick {
    constructor(button) {
        this.button = button;
        this.targetId = this.button.dataset.target;
        this.init();
    }
    init() {
        this.button.addEventListener('click', (e) => {
            e.preventDefault();
            const target = document.getElementById(this.targetId);
            if (target) target.click();
        });
    }
}

export class SelectOnClick {
    constructor(element) {
        this.element = element;
        this.init();
    }
    init() {
        this.element.addEventListener('click', () => {
            this.element.select();
        });
    }
}

export class AccordionCard {
    constructor(card) {
        this.card = card;
        this.toggleBtn = this.card.querySelector('.js-toggle-parent');
        if (this.toggleBtn) this.init();
    }

    init() {
        const isClosed = this.card.classList.contains('is-closed');

        // A11Y: Native Button-Semantik & State für div-basierte Toggles herstellen
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

        // Maus/Touch Interaction
        this.toggleBtn.addEventListener('click', toggleAction);

        // Keyboard Interaction (Space & Enter)
        this.toggleBtn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                toggleAction(e);
            }
        });
    }
}

export class FabRefresh {
    constructor(button) {
        this.button = button;
        this.init();
    }
    init() {
        this.button.addEventListener('click', (e) => {
            e.preventDefault();
            window.location.href =
                window.location.origin + window.location.pathname + window.location.search;
        });
    }
}

export class PrintControls {
    constructor(button) {
        this.button = button;
        this.isClose = this.button.classList.contains('js-close-window');
        this.init();
    }
    init() {
        this.button.addEventListener('click', (e) => {
            e.preventDefault();
            if (this.isClose) {
                window.close();
            } else {
                window.print();
            }
        });
    }
}

export class AutoSubmitSelect {
    constructor(select) {
        this.select = select;
        this.form = this.select.closest('form');
        if (this.form) this.init();
    }
    init() {
        this.select.addEventListener('change', () => {
            if (typeof this.form.requestSubmit === 'function') {
                this.form.requestSubmit();
            } else {
                this.form.submit();
            }
        });
    }
}

export class EventTracker {
    constructor(element) {
        this.element = element;
        this.eventName = this.element.dataset.event;
        this.init();
    }
    init() {
        if (this.eventName && typeof window.dataLayer !== 'undefined') {
            window.dataLayer.push({ event: this.eventName });
        }
    }
}
