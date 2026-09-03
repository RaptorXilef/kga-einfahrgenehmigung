/**
 * Robustes Session-Timer Modul (ES6).
 *
 * Nutzt Date.now() für exaktes Timing, unabhängig vom Browser-Throttling
 * in inaktiven Hintergrund-Tabs. Trennt die Logik sauber vom HTML.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
export class SessionTimer {
    /**
     * @param {string} logoutEndpoint Der API-Endpunkt für den Logout (z.B. 'admin_logout')
     */
    constructor(logoutEndpoint = 'admin_logout') {
        this.logoutEndpoint = logoutEndpoint;

        this.maxIdleMs = 20 * 60 * 1000; // 20 Minuten
        this.warningMs = 3 * 60 * 1000; // 3 Minuten (Warnung ab Minute 17)
        this.lastActivity = Date.now();
        this.isWarningActive = false;

        this.uiTimer = document.getElementById('ui-session-timer');
        this.modalTimer = document.getElementById('modal-session-countdown');
        this.modal = document.getElementById('session-warning-modal');

        this.btnStay = document.getElementById('btn-session-stay');
        this.btnLogout = document.getElementById('btn-session-logout');

        this.init();
    }

    init() {
        // Event Listener für Benutzeraktivität (Ressourcenschonend)
        ['click', 'keyup', 'scroll', 'touchstart'].forEach((evt) =>
            document.addEventListener(evt, () => this.resetIdleTime(), { passive: true })
        );

        // Springt sofort an, wenn der Tab wieder in den Fokus rückt (Background-Throttling Fix)
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.tick();
            }
        });

        // Modal Buttons binden
        if (this.btnStay) {
            this.btnStay.addEventListener('click', () => this.stayLoggedIn());
        }
        if (this.btnLogout) {
            this.btnLogout.addEventListener('click', () => this.logoutNow());
        }

        // Intervall starten
        this.interval = setInterval(() => this.tick(), 1000);
        this.updateDisplay(this.maxIdleMs);
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
        if (!this.isWarningActive) {
            this.lastActivity = Date.now();
            this.updateDisplay(this.maxIdleMs);
        }
    }

    updateDisplay(remainingMs) {
        const timeStr = this.formatTime(remainingMs);
        if (this.uiTimer) {
            this.uiTimer.innerText = timeStr;
            this.uiTimer.style.color =
                remainingMs <= this.warningMs ? 'var(--danger-color)' : 'var(--text-muted)';
        }
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
            if (this.modal) this.modal.style.display = 'flex';
        }

        // Zwangs-Logout
        if (remainingMs <= 0) {
            clearInterval(this.interval);
            this.logoutNow();
        }
    }

    stayLoggedIn() {
        const fd = new FormData();
        fd.append('csrf_token', window.KGA_CONFIG.csrfToken);

        fetch(window.KGA_CONFIG.baseUrl + 'api/ping', {
            method: 'POST',
            body: fd,
        })
            .then(() => {
                this.lastActivity = Date.now();
                this.isWarningActive = false;
                if (this.modal) this.modal.style.display = 'none';
                this.updateDisplay(this.maxIdleMs);
            })
            .catch((err) => console.error('Konnte Session nicht verlängern', err));
    }

    logoutNow() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.KGA_CONFIG.baseUrl + this.logoutEndpoint;

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = window.KGA_CONFIG.csrfToken;

        form.appendChild(csrfInput);
        document.body.appendChild(form);
        form.submit();
    }
}
