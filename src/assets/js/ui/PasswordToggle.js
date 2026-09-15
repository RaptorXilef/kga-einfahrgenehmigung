/**
 * Modulares Toggle für Passwort-Sichtbarkeit (Auge-Icon).
 * Inklusive WAI-ARIA State-Management.
 */
export class PasswordToggle {
    constructor(container) {
        this.container = container;
        this.input = this.container.querySelector('input');
        this.button = this.container.querySelector('button');

        // FIX: GC via AbortController
        this.abortController = new AbortController();

        if (this.input && this.button) {
            this.init();
        }
    }

    init() {
        // A11Y: Keyboard-Fokus wiederherstellen (falls hardcodiert blockiert) und States setzen
        this.button.removeAttribute('tabindex');
        this.button.setAttribute('aria-label', 'Passwort im Klartext anzeigen');
        this.button.setAttribute('aria-pressed', 'false');

        this.button.addEventListener(
            'click',
            (e) => {
                e.preventDefault();
                if (this.input.type === 'password') {
                    this.input.type = 'text';
                    this.button.classList.add('is-active');
                    this.button.setAttribute('aria-label', 'Passwort wieder verbergen');
                    this.button.setAttribute('aria-pressed', 'true');
                } else {
                    this.input.type = 'password';
                    this.button.classList.remove('is-active');
                    this.button.setAttribute('aria-label', 'Passwort im Klartext anzeigen');
                    this.button.setAttribute('aria-pressed', 'false');
                }
            },
            { signal: this.abortController.signal }
        );
    }

    destroy() {
        this.abortController.abort();
    }
}
