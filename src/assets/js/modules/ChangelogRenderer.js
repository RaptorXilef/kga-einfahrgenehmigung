export class ChangelogRenderer {
    constructor(container) {
        this.container = container;
        this.contentArea = this.container.querySelector('.js-content-area');
        const scriptEl = this.container.querySelector('.js-raw-markdown');

        this.rawMarkdown = scriptEl ? scriptEl.textContent : '';

        this.init();
    }

    init() {
        // Performantes Leeren des Containers
        this.contentArea.replaceChildren();

        if (this.rawMarkdown.trim() === 'Kein Changelog gefunden.') {
            const msg = document.createElement('div');
            msg.className = 'u-text-muted u-text-center u-padding-block-l u-font-bold';
            msg.textContent = 'Keine CHANGELOG.md im System gefunden.';
            this.contentArea.appendChild(msg);
            return;
        }

        if (typeof window.marked !== 'undefined' && typeof window.DOMPurify !== 'undefined') {
            const parsedHtml = window.marked.parse(this.rawMarkdown);
            // ARCHITEKTUR-FIX: Generiert ein DocumentFragment anstatt eines Strings!
            const safeFragment = window.DOMPurify.sanitize(parsedHtml, {
                RETURN_DOM_FRAGMENT: true,
            });

            this.contentArea.appendChild(safeFragment);
        } else {
            const errorBox = document.createElement('div');
            errorBox.className = 'c-alert c-alert--danger u-text-center';
            errorBox.textContent = 'Fehler: Markdown Parser nicht geladen.';
            this.contentArea.appendChild(errorBox);
        }
    }
}
