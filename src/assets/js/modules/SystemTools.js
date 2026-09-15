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

    async executeCron(url) {
        // Das confirm() lassen wir bewusst stehen, da es kritische Aktionen
        // sicher blockiert, bis der Nutzer zustimmt.
        if (
            !confirm(
                'Möchten Sie diesen System-Task jetzt manuell ausführen? Dies kann einen Moment dauern.'
            )
        ) {
            return;
        }

        try {
            // Native fetch API absichern durch AbortSignal des Moduls (für Abbrüche beim Tab-Wechsel)
            const response = await fetch(url, {
                method: 'GET',
                signal: this.abortController.signal,
            });

            if (!response.ok) {
                throw new Error(`HTTP Error: Der Server antwortete mit Status ${response.status}`);
            }

            const data = await response.json();

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
