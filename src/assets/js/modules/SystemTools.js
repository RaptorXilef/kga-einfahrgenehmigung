import { api } from '../core/Api.js';
import { notifier } from '../core/Notifier.js';

/**
 * Modul zur Ausführung manueller System-Tasks (Cronjobs).
 */
export class SystemTools {
    constructor(container) {
        this.container = container;
        this.abortController = new AbortController();
        this.init();
    }

    init() {
        // Event-Delegation für alle Cron-Buttons im Container
        this.container.addEventListener(
            'click',
            (e) => {
                const btn = e.target.closest('.js-cron-btn');
                if (btn) {
                    e.preventDefault();
                    this.executeCron(btn.dataset.url);
                }
            },
            { signal: this.abortController.signal }
        );
    }

    /**
     * Erzeugt dynamisch einen barrierefreien HTML5-Dialog,
     * um das blockierende I/O prompt()/confirm() zu ersetzen.
     * SICHER: Ausschließlich native DOM Nodes (Kein innerHTML/XSS Risiko).
     */
    async #promptConfirm(message) {
        return new Promise((resolve) => {
            const dialog = document.createElement('dialog');
            dialog.className = 'c-modal';

            const wrapper = document.createElement('div');
            wrapper.className = 'c-modal__dialog';

            const title = document.createElement('h3');
            title.className = 'c-modal__title';
            title.textContent = 'Aktion bestätigen';

            const desc = document.createElement('p');
            desc.className = 'u-color-muted u-margin-block-start-s';
            desc.textContent = message;

            const btnGroup = document.createElement('div');
            btnGroup.className = 'u-flex u-gap-s u-margin-block-start-l';

            const btnCancel = document.createElement('button');
            btnCancel.type = 'button';
            btnCancel.className = 'c-button c-button--secondary u-flex-grow-1';
            btnCancel.textContent = 'Abbrechen';

            const btnConfirm = document.createElement('button');
            btnConfirm.type = 'button';
            btnConfirm.className = 'c-button c-button--primary u-flex-grow-1';
            btnConfirm.textContent = 'Ausführen';

            btnGroup.append(btnCancel, btnConfirm);
            wrapper.append(title, desc, btnGroup);
            dialog.appendChild(wrapper);
            document.body.appendChild(dialog);

            dialog.showModal();

            const cleanup = (value) => {
                dialog.close();
                dialog.remove();
                resolve(value);
            };

            btnCancel.addEventListener('click', () => cleanup(false));
            btnConfirm.addEventListener('click', () => cleanup(true));
            dialog.addEventListener('cancel', () => cleanup(false));
        });
    }

    async executeCron(url) {
        // ARCHITEKTUR-FIX: Das blockierende window.confirm() durch unseren nativen Dialog ersetzen!
        const isConfirmed = await this.#promptConfirm(
            'Möchten Sie diesen System-Task jetzt manuell ausführen? Dies kann einen Moment dauern.'
        );

        if (!isConfirmed) {
            return;
        }

        try {
            // ARCHITEKTUR-FIX: Wir nutzen den api Singleton für CSRF Handling und POST Methode!
            // Wenn wir hier GET nutzen würden, denkt das Backend (wegen dem Token), es handele sich um
            // einen automatisierten Server-Cronjob und verarbeitet Limits falsch. Durch POST greift das Frontend-Limit.
            const data = await api.post(url);

            let msg = data.message || 'Ausführung abgeschlossen.';

            if (data.processed !== undefined) msg += `\nVerarbeitet: ${data.processed}`;
            if (data.sent_emails !== undefined) msg += `\nGesendet: ${data.sent_emails}`;
            if (data.archived !== undefined) msg += `\nArchiviert: ${data.archived}`;
            if (data.anonymized !== undefined) msg += `\nAnonymisiert: ${data.anonymized}`;

            if (data.success || data.status === 'ok') {
                notifier.show(`Erfolgreich:\n\n${msg}`, 'success');
            } else {
                notifier.show(`Fehler:\n\n${data.error || msg}`, 'error');
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                notifier.show(
                    'Netzwerk- oder Serverfehler bei der Ausführung. Bitte prüfen Sie die PHP Logs.',
                    'error'
                );
                console.error('[SystemTools]', err);
            }
        }
    }

    destroy() {
        this.abortController.abort();
    }
}
