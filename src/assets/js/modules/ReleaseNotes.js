import { api } from '../core/Api.js';

/**
 * Controller für das Release Notes (What's New) Modal.
 * Parst Markdown zu HTML, steuert den manuellen und automatischen Aufruf
 * und sendet den "Gelesen"-Status via API an den Server, falls nötig.
 */
export class ReleaseNotes {
    constructor(container) {
        this.container = container;
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
        // SICHER: Native DOM Manipulation
        if (this.titleElement) {
            this.titleElement.replaceChildren();

            const icon = document.createElement('img');
            icon.className = 'c-icon c-button__icon';
            icon.loading = 'lazy';
            icon.alt = '';
            icon.src = isUnread
                ? `${window.KGA_CONFIG.baseUrl}assets/img/icons/rocket.webp`
                : `${window.KGA_CONFIG.baseUrl}assets/img/icons/book.webp`;

            this.titleElement.appendChild(icon);
            this.titleElement.appendChild(
                document.createTextNode(
                    isUnread ? ' Neu seit Ihrem letzten Login' : ' Release Notes Historie'
                )
            );
        }

        if (this.badgeElement) {
            // Native [hidden] Attribut Nutzung statt .u-hidden
            this.badgeElement.hidden = !isUnread;
        }

        this.contentArea.replaceChildren();

        if (typeof window.marked !== 'undefined' && typeof window.DOMPurify !== 'undefined') {
            if (notesArray.length === 0) {
                const emptyMsg = document.createElement('div');
                emptyMsg.className = 'u-text-center u-color-muted u-padding-around-l';
                emptyMsg.textContent = 'Keine Einträge vorhanden.';
                this.contentArea.appendChild(emptyMsg);
            } else {
                const mainFragment = document.createDocumentFragment();

                notesArray.forEach((note) => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 's-markdown u-margin-block-end-l';

                    const title = document.createElement('h1');
                    title.textContent = `Version ${note.version}`;
                    wrapper.appendChild(title);

                    const parsedHtml = window.marked.parse(note.content);
                    const safeFragment = window.DOMPurify.sanitize(parsedHtml, {
                        RETURN_DOM_FRAGMENT: true,
                    });

                    wrapper.appendChild(safeFragment);
                    mainFragment.appendChild(wrapper);
                });

                this.contentArea.appendChild(mainFragment);
            }
        } else {
            const errorBox = document.createElement('div');
            errorBox.className = 'c-alert c-alert--danger';

            const errorContent = document.createElement('div');
            errorContent.className = 'c-alert__content';
            errorContent.textContent = 'Fehler: Markdown Parser nicht geladen.';

            errorBox.appendChild(errorContent);
            this.contentArea.appendChild(errorBox);
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
