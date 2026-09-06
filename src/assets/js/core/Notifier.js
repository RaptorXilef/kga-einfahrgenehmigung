/**
 * Zentraler Notification-Service (Toasts) als Singleton.
 * Ersetzt verstreute Inline-Toast-Logiken durch eine einheitliche, moderne API.
 */

class NotifierService {
    constructor() {
        this.baseUrl = window.KGA_CONFIG?.baseUrl || '/';
    }

    show(message, type = 'success') {
        // Alte Toasts entfernen, falls noch sichtbar
        const existingToast = document.querySelector('.c-toast');
        if (existingToast) existingToast.remove();

        const toast = document.createElement('div');
        toast.className = 'c-toast';

        // HTML-Gerüst ohne die eigentliche Message
        const iconName = type === 'success' ? 'status-success.webp' : 'status-denied.webp';
        const bgColor = type === 'success' ? '#10b981' : type === 'error' ? '#e11d48' : '#1e293b';

        toast.style.background = bgColor;

        if (type === 'success' || type === 'error') {
            toast.innerHTML = `<img src="${this.baseUrl}assets/img/icons/${iconName}" class="c-icon" style="width:16px; filter: brightness(0) invert(1);"> <span class="js-toast-msg"></span>`;
        } else {
            toast.innerHTML = `<span class="js-toast-msg"></span>`;
        }

        // Sicheres Einfügen der Nachricht als Text!
        toast.querySelector('.js-toast-msg').textContent = message;

        document.body.appendChild(toast);

        // Slide-Out Animation nach 3 Sekunden
        setTimeout(() => {
            toast.classList.add('c-toast--hide');
            setTimeout(() => toast.remove(), 500);
        }, 3000);
    }
}

// Als Singleton exportieren
export const notifier = new NotifierService();
