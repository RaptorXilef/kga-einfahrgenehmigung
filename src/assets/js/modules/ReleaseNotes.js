import { api } from '../core/Api.js';

/**
 * Controller für das Release Notes (What's New) Modal.
 * Parst Markdown zu HTML, steuert den manuellen und automatischen Aufruf
 * und sendet den "Gelesen"-Status via API an den Server, falls nötig.
 */
export class ReleaseNotes {
    constructor(container) {
        this.container = container;
        // Strikte js-* Selektoren
        this.contentArea = this.container.querySelector('.js-release-notes-content');
        this.closeBtns = this.container.querySelectorAll('.js-close-release-notes');
        this.titleElement = this.container.querySelector('.js-release-notes-title');
        this.badgeElement = this.container.querySelector('.js-release-notes-badge');

        // Zentraler Controller für restlose Garbage Collection
        this.abortController = new AbortController();

        // Strikte Selektoren für die Config/JSON Container
        const unreadScript = document.querySelector('.js-release-notes-unread-data');
        const allScript = document.querySelector('.js-release-notes-all-data');

        // Defensive Error Boundaries bei JSON Injektion
        try {
            this.unreadNotes = unreadScript ? JSON.parse(unreadScript.textContent || '[]') : [];
        } catch {
            this.unreadNotes = [];
        }

        try {
            this.allNotes = allScript ? JSON.parse(allScript.textContent || '[]') : [];
        } catch {
            this.allNotes = [];
        }

        // Button zum manuellen Öffnen (z.B. im Dashboard Header)
        this.triggerBtns = document.querySelectorAll('.js-show-all-release-notes');

        // Merker, in welchem Modus das Modal aktuell ist
        this.showingUnread = false;

        this.init();
    }

    init() {
        const options = { signal: this.abortController.signal };

        this.closeBtns.forEach((btn) => {
            btn.addEventListener(
                'click',
                (e) => {
                    e.preventDefault();
                    // Modal über State-Klasse schließen
                    this.container.close();

                    // Wenn wir gerade unread notes angezeigt haben, als gelesen in DB markieren!
                    if (this.showingUnread && this.unreadNotes.length > 0) {
                        // Wir übergeben immer die höchste (neueste) Versionsnummer, die im Array an Position 0 steht
                        this.markAsRead(this.unreadNotes[0].version);
                        this.unreadNotes = []; // Leeren, damit beim nächsten Klick auf "Alle" nicht neu in DB gespeichert wird
                    }
                },
                options
            );
        });

        this.container.addEventListener(
            'click',
            (e) => {
                if (e.target === this.container) {
                    this.container.close();
                    if (this.showingUnread && this.unreadNotes.length > 0) {
                        this.markAsRead(this.unreadNotes[0].version);
                        this.unreadNotes = [];
                    }
                }
            },
            options
        );

        // Event Listener für manuelles Öffnen durch den Button im Dashboard
        this.triggerBtns.forEach((btn) => {
            btn.addEventListener(
                'click',
                (e) => {
                    e.preventDefault();
                    // Manuelles Öffnen -> Zeige IMMER alle Notes an und deaktiviere den DB-Speicher-Trigger
                    this.openModal(this.allNotes, false);
                },
                options
            );
        });

        // Automatisches Öffnen nach Login (Wenn ungelesene Notes vorhanden sind)
        if (this.unreadNotes.length > 0) {
            this.openModal(this.unreadNotes, true);
        }
    }

    openModal(notesArray, isUnread) {
        this.showingUnread = isUnread;

        // Titel und Badge dynamisch anpassen
        if (this.titleElement) {
            // Emojis entfernt und durch WebP-Icons ersetzt + Lazy Loading!
            this.titleElement.innerHTML = isUnread
                ? `<img src="${window.KGA_CONFIG.baseUrl}assets/img/icons/rocket.webp" class="c-icon c-button__icon" loading="lazy" alt=""> Neu seit Ihrem letzten Login`
                : `<img src="${window.KGA_CONFIG.baseUrl}assets/img/icons/book.webp" class="c-icon c-button__icon" loading="lazy" alt=""> Release Notes Historie`;
        }

        if (this.badgeElement) {
            // Native [hidden] Attribut Nutzung statt .u-hidden
            this.badgeElement.hidden = !isUnread;
        }

        // Rendern des Markdowns (Mit Fallback falls CDN blockiert)
        let html = '';
        if (typeof window.marked !== 'undefined' && typeof window.DOMPurify !== 'undefined') {
            if (notesArray.length === 0) {
                html =
                    '<div class="u-text-center u-color-muted u-padding-around-l">Keine Einträge vorhanden.</div>';
            } else {
                notesArray.forEach((note) => {
                    // Auch die interpolierte Version muss zwingend durch den Sanitizer!
                    const safeVersion = window.DOMPurify.sanitize(note.version);
                    const safeContent = window.DOMPurify.sanitize(
                        window.marked.parse(note.content)
                    );

                    html += `<div class="s-markdown u-margin-block-end-l">`;
                    html += `<h1>Version ${safeVersion}</h1>`;
                    html += safeContent;
                    html += `</div>`;
                });
            }
            this.contentArea.innerHTML = html;
        } else {
            this.contentArea.innerHTML =
                '<div class="c-alert c-alert--danger"><div class="c-alert__content">Fehler: Markdown Parser nicht geladen.</div></div>';
        }

        this.container.showModal();
    }

    async markAsRead(version) {
        try {
            // Wir nutzen URLSearchParams, damit die KGA Api Klasse es als sauberes POST sendet
            const params = new URLSearchParams();
            params.append('version', version);
            params.append('csrf_token', window.KGA_CONFIG.csrfToken);

            await api.post('api/mark_changelog_read', params);
        } catch (e) {
            console.error('[ReleaseNotes] Konnte Changelog nicht als gelesen markieren', e);
        }
    }

    destroy() {
        this.abortController.abort();
    }
}
