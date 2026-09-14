import { api } from '../core/Api.js';
import { notifier } from '../core/Notifier.js';
import { throttle } from '../utils/Utils.js';

/**
 * Robustes Session-Timer Modul (ES2026).
 * Nutzt den AbortController für restlose Event-Listener Garbage-Collection.
 */
export class SessionTimer {
    constructor(rootElement) {
        this.container = rootElement;
        this.maxIdleMs = 20 * 60 * 1000; // 20 Minuten
        this.warningMs = 3 * 60 * 1000; // 3 Minuten Warn-Zeitraum
        this.lastActivity = Date.now();
        this.isWarningActive = false;

        // DOM Elemente (liegen bereits statisch im HTML, z.B. in header_nav.phtml)
        this.modalTimer = document.getElementById('modal-session-countdown');
        this.modal = document.getElementById('session-warning-modal');
        this.btnStay = document.getElementById('btn-session-stay');
        this.btnLogout = document.getElementById('btn-session-logout');

        // Bestimme Logout-Route dynamisch (Admin vs History)
        this.isHistoryMode = window.location.pathname.includes('/history');
        this.logoutEndpoint = this.isHistoryMode ? 'history_logout' : 'admin_logout';

        // Stabile Referenzen für EventListener abspeichern (Garbage Collection fähig)
        this.boundResetIdleTime = throttle(() => this.resetIdleTime(), 5000);
        this.boundVisibilityChange = () => {
            if (document.visibilityState === 'visible') this.syncWithStorage();
        };
        this.boundStorageChange = (e) => {
            if (e.key === 'kga_last_activity') this.syncWithStorage();
        };
        this.boundStayLoggedIn = () => this.stayLoggedIn();
        this.boundLogoutNow = () => this.logoutNow();

        // Zentrale Verwaltung aller Listener für sauberes Unmounting
        this.abortController = new AbortController();

        this.init();
    }

    getStoredActivity() {
        try {
            return parseInt(localStorage.getItem('kga_last_activity') || '0', 10);
        } catch {
            return 0; // Rückfallwert bei blockiertem LocalStorage
        }
    }

    setStoredActivity(timestamp) {
        try {
            localStorage.setItem('kga_last_activity', timestamp.toString());
        } catch {
            // Ignore
        }
    }

    init() {
        const storedActivity = this.getStoredActivity();
        if (storedActivity > this.lastActivity) {
            this.lastActivity = storedActivity;
        } else {
            this.setStoredActivity(this.lastActivity);
        }

        // Intervall starten
        this.interval = setInterval(() => this.tick(), 1000);
        this.updateDisplay(this.maxIdleMs);

        const options = { passive: true, signal: this.abortController.signal };

        ['click', 'keyup', 'scroll', 'touchstart'].forEach((evt) => {
            document.addEventListener(evt, this.boundResetIdleTime, options);
        });

        document.addEventListener('visibilitychange', this.boundVisibilityChange, options);
        window.addEventListener('storage', this.boundStorageChange, options);

        if (this.btnStay) this.btnStay.addEventListener('click', this.boundStayLoggedIn, options);
        if (this.btnLogout) this.btnLogout.addEventListener('click', this.boundLogoutNow, options);
    }

    destroy() {
        clearInterval(this.interval);
        // Beendet alle angehängten Event-Listener auf einen Schlag!
        this.abortController.abort();
    }

    syncWithStorage() {
        const stored = this.getStoredActivity();
        if (stored > this.lastActivity) {
            this.lastActivity = stored;
            if (this.isWarningActive) {
                this.isWarningActive = false;
                if (this.modal && this.modal.open) this.modal.close();
            }
        }
        this.tick();
    }

    formatTime(ms) {
        const totalSeconds = Math.max(0, Math.floor(ms / 1000));
        const m = Math.floor(totalSeconds / 60)
            .toString()
            .padStart(2, '0');
        const s = (totalSeconds % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    }

    resetIdleTime() {
        if (this.isWarningActive) return; // Wenn Warnung an ist, muss Button geklickt werden

        this.lastActivity = Date.now();
        this.setStoredActivity(this.lastActivity);
        this.updateDisplay(this.maxIdleMs);
    }

    updateDisplay(remainingMs) {
        const timeStr = this.formatTime(remainingMs);

        // Aktualisiere das Nav-Label
        if (this.container) {
            this.container.innerText = timeStr;
            this.container.classList.toggle('is-error', remainingMs <= this.warningMs);
        }

        // Aktualisiere Modal-Countdown
        if (this.modalTimer && this.isWarningActive) {
            this.modalTimer.innerText = timeStr;
        }
    }

    tick() {
        const now = Date.now();
        const idleMs = now - this.lastActivity;
        const remainingMs = this.maxIdleMs - idleMs;

        this.updateDisplay(remainingMs);

        // Warnung einblenden
        if (remainingMs <= this.warningMs && remainingMs > 0 && !this.isWarningActive) {
            this.isWarningActive = true;
            if (this.modal && !this.modal.open) this.modal.showModal();
        }

        // Zwangs-Logout
        if (remainingMs <= 0) {
            this.destroy(); // Sauberes Aufräumen vor dem Logout
            this.logoutNow();
        }
    }

    async stayLoggedIn() {
        try {
            // Ping an den Server via Api-Singleton, um die PHP-Session am Leben zu halten
            const result = await api.post('api/ping');

            if (result.success) {
                this.lastActivity = Date.now();
                this.setStoredActivity(this.lastActivity);
                this.isWarningActive = false;

                if (this.modal && this.modal.open) this.modal.close();
                this.updateDisplay(this.maxIdleMs);
                notifier.show('Sitzung erfolgreich verlängert.');
            } else {
                this.logoutNow();
            }
        } catch (err) {
            console.error('[SessionTimer] Konnte Session nicht verlängern', err);
            notifier.show('Verbindungsfehler beim Verlängern der Sitzung.', 'error');
        }
    }

    logoutNow() {
        // Fallback: Sicheres Logout via unsichtbarem Form-POST, um CSRF-Checks zu umgehen/zu nutzen
        const form = document.createElement('form');
        form.method = 'POST';

        // Optional Chaining schützt vor TypeError bei fehlender Global-Config
        const baseUrl = window.KGA_CONFIG?.baseUrl || '/';
        const csrfToken = window.KGA_CONFIG?.csrfToken || '';

        form.action = baseUrl + this.logoutEndpoint;

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken;

        form.appendChild(csrfInput);
        document.body.appendChild(form);
        form.submit();
    }
}
