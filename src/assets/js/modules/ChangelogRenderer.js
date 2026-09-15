export class ChangelogRenderer {
    constructor(container) {
        this.container = container;

        // FIX: Strikte BEM JS-Hooks
        this.contentArea = this.container.querySelector('.js-content-area');
        const scriptEl = this.container.querySelector('.js-raw-markdown');

        this.rawMarkdown = scriptEl ? scriptEl.textContent : '';

        this.init();
    }

    init() {
        if (this.rawMarkdown.trim() === 'Kein Changelog gefunden.') {
            this.contentArea.innerHTML =
                '<div class="u-text-muted u-text-center u-padding-block-l u-font-bold">Keine CHANGELOG.md im System gefunden.</div>';
            return;
        }

        if (typeof window.marked !== 'undefined' && typeof window.DOMPurify !== 'undefined') {
            const html = window.marked.parse(this.rawMarkdown);
            this.contentArea.innerHTML = window.DOMPurify.sanitize(html);
        } else {
            this.contentArea.innerHTML =
                '<div class="c-alert c-alert--danger u-text-center">Fehler: Markdown Parser nicht geladen.</div>';
        }
    }
}
