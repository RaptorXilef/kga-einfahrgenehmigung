import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '../../src/assets/js/core/Api.js';
import { notifier } from '../../src/assets/js/core/Notifier.js';
import { SessionTimer } from '../../src/assets/js/ui/SessionTimer.js';

// Mocking externer Abhängigkeiten
vi.mock('../../src/assets/js/core/Api.js', () => ({
    api: { post: vi.fn() },
}));

vi.mock('../../src/assets/js/core/Notifier.js', () => ({
    notifier: { show: vi.fn() },
}));

describe('SessionTimer', () => {
    let timerInstance;
    let container;
    let modal;
    let modalTimer;

    beforeEach(() => {
        // Fake Timers aktivieren für Zeitsprünge
        vi.useFakeTimers();

        // DOM Setup (Nachbau aus header_nav.phtml)
        document.body.innerHTML = `
            <span class="js-session-timer">20:00</span>
            <dialog class="c-modal js-session-warning-modal">
                <strong class="js-session-countdown">03:00</strong>
                <button class="js-session-stay">Bleiben</button>
            </dialog>
        `;

        container = document.querySelector('.js-session-timer');
        modal = document.querySelector('.js-session-warning-modal');
        modalTimer = document.querySelector('.js-session-countdown');

        // JSDOM hat oft noch keine native Dialog-API implementiert, daher mocken wir sie ans Element
        modal.showModal = vi.fn(function () {
            this.open = true;
        });
        modal.close = vi.fn(function () {
            this.open = false;
        });

        // LocalStorage mocken
        const store = {};
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation((key, value) => {
            store[key] = value;
        });
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation((key) => store[key] || null);

        // Instanziierung
        timerInstance = new SessionTimer(container);
    });

    afterEach(() => {
        timerInstance.destroy();
        vi.restoreAllMocks();
        vi.useRealTimers();
    });

    it('sollte korrekt initialisieren und 20:00 anzeigen', () => {
        expect(container.innerText).toBe('20:00');
        expect(timerInstance.isWarningActive).toBe(false);
    });

    it('sollte das Modal öffnen, wenn die Restzeit unter 3 Minuten fällt', () => {
        // 17 Minuten und 1 Sekunde in die Zukunft springen (1021 Sekunden)
        vi.advanceTimersByTime(17 * 60 * 1000 + 1000);

        expect(timerInstance.isWarningActive).toBe(true);
        expect(modal.showModal).toHaveBeenCalledTimes(1);
        expect(modal.open).toBe(true);
        expect(modalTimer.innerText).toBe('02:59');
        expect(container.classList.contains('is-error')).toBe(true);
    });

    it('sollte die Zeit zurücksetzen und das Modal schließen, wenn der Benutzer bleiben möchte', async () => {
        // API Ping Mock für Erfolg konfigurieren
        api.post.mockResolvedValue({ success: true });

        // In die Warn-Zone springen
        vi.advanceTimersByTime(17 * 60 * 1000 + 1000);
        expect(modal.open).toBe(true);

        // Aufruf der Methode direkt, anstatt Click Event aufzubauen
        await timerInstance.stayLoggedIn();

        // Prüfen, ob API aufgerufen wurde
        expect(api.post).toHaveBeenCalledWith('api/ping');

        // Prüfen, ob UI zurückgesetzt wurde
        expect(timerInstance.isWarningActive).toBe(false);
        expect(modal.close).toHaveBeenCalledTimes(1);
        expect(container.innerText).toBe('20:00');
        expect(container.classList.contains('is-error')).toBe(false);

        // Prüfen, ob Notifier getriggert wurde
        expect(notifier.show).toHaveBeenCalledWith('Sitzung erfolgreich verlängert.');
    });

    it('sollte einen Zwangs-Logout auslösen, wenn der Timer 0 erreicht', () => {
        // Logout-Funktion spyen (da sie ein form submit ausführt)
        const logoutSpy = vi.spyOn(timerInstance, 'logoutNow').mockImplementation(() => {});

        // Genau 20 Minuten vorspulen
        vi.advanceTimersByTime(20 * 60 * 1000);

        expect(logoutSpy).toHaveBeenCalledTimes(1);
    });

    it('sollte Tastatur/Maus-Aktivität vor der Warnung registrieren und den Timer zurücksetzen', () => {
        // 10 Minuten vorspulen
        vi.advanceTimersByTime(10 * 60 * 1000);
        expect(container.innerText).toBe('10:00');

        // Klick auf Body simulieren
        document.dispatchEvent(new Event('click'));

        // Timer sollte direkt (aufgrund des Throttlings innerhalb von 5s) wieder bei 20:00 sein
        expect(container.innerText).toBe('20:00');
    });
});
