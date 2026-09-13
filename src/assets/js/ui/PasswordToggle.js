/**
 * Modulares Toggle für Passwort-Sichtbarkeit (Auge-Icon).
 * Ersetzt das alte inline onclick="togglePassword(event)".
 */
export class PasswordToggle {
    constructor(container) {
        this.container = container;
        this.input = this.container.querySelector('input');
        this.button = this.container.querySelector('button');

        if (this.input && this.button) {
            this.init();
        }
    }

    init() {
        this.button.addEventListener('click', (e) => {
            e.preventDefault();
            if (this.input.type === 'password') {
                this.input.type = 'text';
                this.button.classList.add('c-password-toggle__btn--active');
            } else {
                this.input.type = 'password';
                this.button.classList.remove('c-password-toggle__btn--active');
            }
        });
    }
}
