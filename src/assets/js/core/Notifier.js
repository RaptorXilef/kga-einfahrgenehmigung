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

        // Styling je nach Typ
        if (type === 'success') {
            toast.style.background = '#10b981'; // Grün
            toast.innerHTML = `<img src="${this.baseUrl}assets/img/icons/status-success.webp" class="c-icon" style="width:16px; filter: brightness(0) invert(1);"> ${message}`;
        } else if (type === 'error') {
            toast.style.background = '#e11d48'; // Rot
            toast.innerHTML = `<img src="${this.baseUrl}assets/img/icons/status-denied.webp" class="c-icon" style="width:16px; filter: brightness(0) invert(1);"> ${message}`;
        } else {
            toast.style.background = '#1e293b'; // Standard Dark
            toast.innerHTML = message;
        }

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
