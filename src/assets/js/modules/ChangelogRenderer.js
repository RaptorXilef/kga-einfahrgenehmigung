/**
 * Übersetzt das rohe Markdown der CHANGELOG.md über externe Libraries in sicheres HTML.
 */
export class ChangelogRenderer {
    constructor(container) {
        this.container = container;
        this.contentArea = this.container.querySelector('#content-area');

        const scriptEl = this.container.querySelector('#raw-markdown');
        this.rawMarkdown = scriptEl ? scriptEl.textContent : '';

        this.init();
    }

    init() {
        if (this.rawMarkdown.trim() === 'Kein Changelog gefunden.') {
            this.contentArea.innerHTML =
                '<div class="u-text-muted u-text-center u-padding-y-l u-font-bold">Keine CHANGELOG.md im System gefunden.</div>';
            return;
        }

        // Wir erwarten, dass marked und DOMPurify über das CDN in der PHTML geladen wurden
        if (typeof marked !== 'undefined' && typeof DOMPurify !== 'undefined') {
            const html = marked.parse(this.rawMarkdown);
            this.contentArea.innerHTML = DOMPurify.sanitize(html);
        } else {
            this.contentArea.innerHTML =
                '<div class="u-color-error u-text-center">Fehler: Markdown Parser nicht geladen.</div>';
        }
    }
}
