/**
 * Modulares BEM-Toggle für den Hell/Dunkel Modus.
 * Beachtet System-Präferenzen und speichert die Auswahl persistent.
 */
export class ThemeToggle {
    constructor(container) {
        this.button = container;
        this.icon = this.button.querySelector('.js-theme-icon');
        this.text = this.button.querySelector('.js-theme-text');
        this.STORAGE_KEY = 'kga_theme_preference';

        this.init();
    }

    init() {
        const savedTheme = localStorage.getItem(this.STORAGE_KEY);
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        let activeTheme = savedTheme || (systemPrefersDark ? 'dark' : 'light');

        this.applyTheme(activeTheme);

        this.button.addEventListener('click', (e) => {
            e.preventDefault();
            activeTheme =
                document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            this.applyTheme(activeTheme);
            localStorage.setItem(this.STORAGE_KEY, activeTheme);
        });

        // Lauscht auf Systemänderungen, falls der Nutzer nichts manuell überschrieben hat
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem(this.STORAGE_KEY)) {
                this.applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        if (this.icon && this.text) {
            if (theme === 'dark') {
                this.icon.src = this.icon.src.replace('icon-sun', 'icon-moon');
                this.text.innerText = 'Dark';
            } else {
                this.icon.src = this.icon.src.replace('icon-moon', 'icon-sun');
                this.text.innerText = 'Light';
            }
        }
    }
}
