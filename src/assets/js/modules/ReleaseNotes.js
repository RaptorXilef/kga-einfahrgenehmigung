import { api } from '../core/Api.js';

/**
 * Controller für das Release Notes (What's New) Modal.
 * Parst Markdown zu HTML und sendet den "Gelesen"-Status via API an den Server.
 */
export class ReleaseNotes {
    constructor(container) {
        this.container = container;
        this.contentArea = this.container.querySelector('#release-notes-content');
        this.closeBtns = this.container.querySelectorAll('.js-close-release-notes');

        const dataScript = document.getElementById('release-notes-data');
        this.notes = dataScript ? JSON.parse(dataScript.textContent || '[]') : [];

        if (this.notes.length > 0) {
            this.init();
        }
    }

    init() {
        // 1. Markdown sicher rendern
        let html = '';
        if (typeof window.marked !== 'undefined' && typeof window.DOMPurify !== 'undefined') {
            this.notes.forEach((note) => {
                html += `<div class="rn-markdown u-margin-bottom-l">`;
                html += `<h1>Version ${note.version}</h1>`;
                html += window.DOMPurify.sanitize(window.marked.parse(note.content));
                html += `</div>`;
            });
            this.contentArea.innerHTML = html;
        } else {
            this.contentArea.innerHTML =
                '<div class="c-alert c-alert--danger">Fehler: Markdown Parser nicht geladen.</div>';
        }

        // 2. Buttons binden (Das rote X und der dicke Button unten)
        this.closeBtns.forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const version = btn.dataset.version;
                this.markAsRead(version);
            });
        });
    }

    async markAsRead(version) {
        // Modal sofort ausblenden, damit es sich für den User schnell (snappy) anfühlt
        this.container.style.display = 'none';

        try {
            // Wir nutzen URLSearchParams, damit die API Klasse es als sauberes POST sendet
            const params = new URLSearchParams();
            params.append('version', version);
            params.append('csrf_token', window.KGA_CONFIG.csrfToken);

            await api.post('api/mark_changelog_read', params);
        } catch (e) {
            console.error('[ReleaseNotes] Konnte Changelog nicht als gelesen markieren', e);
        }
    }
}
