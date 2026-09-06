/**
 * Modul zur Ausführung manueller System-Tasks (Cronjobs).
 * Ersetzt die alte executeCron() Inline-Funktion in tab_system.phtml.
 */
export class SystemTools {
    constructor(container) {
        this.container = container;
        this.init();
    }

    init() {
        // Event-Delegation für alle Cron-Buttons im Container
        this.container.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-cron-execute');
            if (btn) {
                e.preventDefault();
                this.executeCron(btn.dataset.url);
            }
        });
    }

    async executeCron(url) {
        if (
            !confirm(
                'Möchten Sie diesen System-Task jetzt manuell ausführen? Dies kann einen Moment dauern.'
            )
        ) {
            return;
        }

        try {
            // Cron-URLs erfordern zwingend GET und haben eigene Tokens,
            // daher nutzen wir hier nativ fetch anstatt der KGA-API.
            const response = await fetch(url, { method: 'GET' });
            const data = await response.json();

            let msg = data.message || 'Ausführung abgeschlossen.';

            if (data.processed !== undefined) msg += '\nVerarbeitet: ' + data.processed;
            if (data.sent_emails !== undefined) msg += '\nGesendet: ' + data.sent_emails;
            if (data.archived !== undefined) msg += '\nArchiviert: ' + data.archived;
            if (data.anonymized !== undefined) msg += '\nAnonymisiert: ' + data.anonymized;

            if (data.success || data.status === 'ok') {
                alert(`✅ Erfolgreich:\n\n${msg}`);
            } else {
                alert(`❌ Fehler:\n\n${data.error || msg}`);
            }
        } catch (err) {
            alert(
                '❌ Netzwerk- oder Serverfehler bei der Ausführung. Bitte prüfen Sie die PHP Logs.'
            );
            console.error('[SystemTools]', err);
        }
    }
}
