/**
 * Zentraler Notification-Service (Toasts) als Singleton.
 * Ersetzt verstreute Inline-Toast-Logiken durch eine einheitliche, moderne API.
 */

class NotifierService {
    constructor() {
        this.baseUrl = window.KGA_CONFIG?.baseUrl || '/';
        // Speichere Timer-IDs, um Memory Leaks durch Zombie-Closures zu verhindern
        this.hideTimeout = null;
        this.removeTimeout = null;
    }

    show(message, type = 'success') {
        // Alte Timer stoppen!
        if (this.hideTimeout) clearTimeout(this.hideTimeout);
        if (this.removeTimeout) clearTimeout(this.removeTimeout);

        // Alte Toasts entfernen, falls noch sichtbar
        const existingToast = document.querySelector('.c-toast');
        if (existingToast) existingToast.remove();

        const toast = document.createElement('div');
        // FIX: Saubere BEM Modifikatoren statt Inline-Styles
        toast.className = `c-toast c-toast--${type}`;

        // HTML-Gerüst ohne die eigentliche Message
        const iconName = type === 'success' ? 'status-success.webp' : 'status-denied.webp';

        if (type === 'success' || type === 'error') {
            toast.innerHTML = `<img src="${this.baseUrl}assets/img/icons/${iconName}" class="c-icon c-toast__icon" alt=""> <span class="c-toast__msg js-toast-msg"></span>`;
        } else {
            toast.innerHTML = `<span class="c-toast__msg js-toast-msg"></span>`;
        }

        // Sicheres Einfügen der Nachricht als Text!
        const msgContainer = toast.querySelector('.js-toast-msg');
        msgContainer.textContent = message;

        document.body.appendChild(toast);

        // Slide-Out Animation nach 3 Sekunden
        this.hideTimeout = setTimeout(() => {
            toast.classList.add('is-hidden');
            this.removeTimeout = setTimeout(() => toast.remove(), 500);
        }, 3000);
    }
}

// Als Singleton exportieren
export const notifier = new NotifierService();
