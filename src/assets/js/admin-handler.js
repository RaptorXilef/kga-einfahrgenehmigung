/**
 * @file admin-handler.js
 * Steuert die interaktiven Funktionen des Admin-Dashboards (v0.7.0).
 */

export class AdminDashboardHandler {
    constructor() {
        this.searchInput = document.getElementById('adminSearch');
        this.tableRows = document.querySelectorAll('.c-table tbody tr');

        if (this.searchInput) {
            this.init();
        }
    }

    init() {
        // Event Listener für die Suche
        this.searchInput.addEventListener('input', (e) => this.filterTables(e.target.value));
    }

    /**
     * Filtert alle Tabellenzeilen basierend auf dem Suchbegriff.
     */
    filterTables(searchTerm) {
        const query = searchTerm.toLowerCase().trim();

        this.tableRows.forEach((row) => {
            // Wir durchsuchen den gesamten Textgehalt der Zeile (Name, Code, KFZ)
            const text = row.textContent.toLowerCase();

            if (text.includes(query)) {
                row.style.display = ''; // Zeigen
            } else {
                row.style.display = 'none'; // Verstecken
            }
        });

        // Optional: Feedback geben, falls in einem Tab nichts gefunden wurde
        this.updateTabCounts();
    }

    /**
     * Aktualisiert (optional) die Anzeige, wie viele Treffer gefunden wurden.
     */
    updateTabCounts() {
        // Hier könnte man später die Zahlen in den Tab-Buttons (z.B. "Aktiv (5)")
        // dynamisch anpassen, damit der Admin sieht, wo Treffer liegen.
    }
}

// Initialisierung
document.addEventListener('DOMContentLoaded', () => {
    window.adminHandler = new AdminDashboardHandler();
});

/**
 * Tab-Logik (Global verfügbar halten für onclick in HTML)
 */
window.openTab = (evt, tabId) => {
    const contents = document.getElementsByClassName('c-tabs__content');
    for (let i = 0; i < contents.length; i++) {
        contents[i].classList.remove('c-tabs__content--active');
    }
    const buttons = document.getElementsByClassName('c-tabs__btn');
    for (let i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove('c-tabs__btn--active');
    }
    document.getElementById(tabId).classList.add('c-tabs__content--active');
    evt.currentTarget.classList.add('c-tabs__btn--active');
};
