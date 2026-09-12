/**
 * Modulares BEM-Toggle für den Hell/Dunkel Modus.
 * Beachtet System-Präferenzen, speichert die Auswahl persistent und steuert das Icon-Feedback (Ziel-Status).
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
        // OS-Level Abfrage (Windows, Mac, iOS, Android System-Darkmode)
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        // Fallback: Wenn noch nie etwas geklickt wurde, richte dich nach dem System
        let activeTheme = savedTheme || (systemPrefersDark ? 'dark' : 'light');

        this.applyTheme(activeTheme);

        this.button.addEventListener('click', (e) => {
            e.preventDefault();
            activeTheme =
                document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            this.applyTheme(activeTheme);
            localStorage.setItem(this.STORAGE_KEY, activeTheme);
        });

        // Wenn der Nutzer seine System-Einstellungen ändert, währen die Seite offen ist, reagieren wir!
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            // Aber nur, wenn der Nutzer das Theme nicht manuell überschrieben hat
            if (!localStorage.getItem(this.STORAGE_KEY)) {
                this.applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);

        if (this.icon && this.text) {
            const baseUrl = window.KGA_CONFIG?.baseUrl || '/';

            if (theme === 'dark') {
                // Im Dark Mode zeigen wir die Sonne (als Hinweis: "Klick mich für Light Mode")
                this.icon.src = `${baseUrl}assets/img/icons/icon-sun.webp`;
                this.text.innerText = 'Hell';
            } else {
                // Im Light Mode zeigen wir den Mond (als Hinweis: "Klick mich für Dark Mode")
                this.icon.src = `${baseUrl}assets/img/icons/icon-moon.webp`;
                this.text.innerText = 'Dunkel';
            }
        }
    }
}
