/**
 * Engine für clientseitige interaktive Tabellensortierungen.
 * Scannt strukturierte Klassen (`.js-sort-table`), injiziert grafische Sortierungs-Indikatoren (⇅, ↓, ↑),
 * speichert die Standard-Reihenfolge (`originalRows`) für Resets und sortiert Spalten dynamisch
 * nach numerischen Werten (mittels data-sort-val) oder alphanumerischen Strings.
 * Kontext: Universelles Usability-Plugin für Admin- und Statistik-Tabellen.
 *
 * Path: src/assets/js/table-sorter.js
 * Path: public/assets/js/table-sorter.min.js
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
class TableSorter {
    constructor() {
        this.tables = document.querySelectorAll('.js-sort-table');
        this.init();
    }

    /**
     * Scannt DOM-Tabellen, cached die Initial-Reihenfolge der Zeilen in `table.originalRows`
     * und bindet Klick-Listener an sortierfähige Tabellenköpfe (`th.js-sort-header`).
     * Skips Tabellen, die Colspans (z.B. leere Tabellen-Meldungen) enthalten.
     *
     * @return {void}
     */
    init() {
        this.tables.forEach((table) => {
            const tbody = table.querySelector('tbody');
            const headers = table.querySelectorAll('th.js-sort-header');

            // Wenn die Tabelle leer ist (Meldung über colspan), nicht sortieren
            if (!tbody || headers.length === 0 || tbody.querySelector('td[colspan]')) return;

            // Originale Reihenfolge für den "Reset" (3. Klick) speichern
            table.originalRows = Array.from(tbody.querySelectorAll('tr'));

            headers.forEach((th, index) => {
                th.style.cursor = 'pointer';
                th.style.userSelect = 'none';
                th.title = 'Klicken zum Sortieren';

                // Icon hinzufügen
                th.innerHTML +=
                    ' <span class="sort-icon" style="opacity:0.3; font-size:1em; margin-left: 4px; display:inline-block; vertical-align:middle;">⇅</span>';

                th.addEventListener('click', () => this.sortTable(table, th, index));
            });
        });
    }

    /**
     * Steuert den Sortierungsprozess einer Spalte nach dem Drei-Wege-Prinzip (Aufsteigend -> Absteigend -> Reset).
     * Setzt Zustandsklassen an Header-Elementen, aktualisiert visuelle SVG/Unicode-Pfeile,
     * liest `data-sort-val` Attribute aus und ordnet die Tabellenzeilen (TRs) über Array-Mutationen neu an.
     *
     * @param {HTMLTableElement} table       Das betroffene Tabellen-DOM-Objekt.
     * @param {HTMLTableCellElement} th      Der geklickte Tabellenkopf (Spaltenüberschrift).
     * @param {number}           columnIndex Der nullbasierte Index der Spalte im Zeilen-Verbund.
     *
     * @return {void}
     */
    sortTable(table, th, columnIndex) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const headers = table.querySelectorAll('th.js-sort-header');

        // Aktuellen Status ermitteln
        const currentSort = th.getAttribute('data-sort-dir') || 'none';
        let nextSort = 'asc';

        if (currentSort === 'asc') nextSort = 'desc';
        else if (currentSort === 'desc') nextSort = 'none';

        // Alle Icons & Stati zurücksetzen
        headers.forEach((header) => {
            header.setAttribute('data-sort-dir', 'none');
            const icon = header.querySelector('.sort-icon');
            if (icon) {
                icon.innerHTML = '⇅';
                icon.style.opacity = '0.3';
            }
        });

        if (nextSort === 'none') {
            // 3. Klick: Originalzustand wiederherstellen
            table.originalRows.forEach((row) => {
                tbody.appendChild(row);
            });
        } else {
            // 1. oder 2. Klick: Sortieren
            th.setAttribute('data-sort-dir', nextSort);
            const icon = th.querySelector('.sort-icon');
            if (icon) {
                icon.innerHTML = nextSort === 'asc' ? '↓' : '↑';
                icon.style.opacity = '1';
                icon.style.color = 'var(--primary-color)';
            }

            rows.sort((a, b) => {
                const cellA = a.querySelectorAll('td')[columnIndex];
                const cellB = b.querySelectorAll('td')[columnIndex];

                if (!cellA || !cellB) return 0;

                // Wir nutzen das data-sort-val Attribut, falls vorhanden
                let valA = cellA.getAttribute('data-sort-val');
                let valB = cellB.getAttribute('data-sort-val');

                if (valA === null) valA = cellA.innerText.trim();
                if (valB === null) valB = cellB.innerText.trim();

                // Erkennung von Zahlen (z.B. für Preise)
                const numA = parseFloat(valA);
                const numB = parseFloat(valB);
                if (
                    !Number.isNaN(numA) &&
                    !Number.isNaN(numB) &&
                    valA.match(/^-?\d+(\.\d+)?$/) &&
                    valB.match(/^-?\d+(\.\d+)?$/)
                ) {
                    return nextSort === 'asc' ? numA - numB : numB - numA;
                }

                // String Sortierung (Case Insensitive)
                valA = valA.toLowerCase();
                valB = valB.toLowerCase();

                if (valA < valB) return nextSort === 'asc' ? -1 : 1;
                if (valA > valB) return nextSort === 'asc' ? 1 : -1;
                return 0;
            });

            // Sortierte Zeilen neu ins DOM einfügen
            rows.forEach((row) => {
                tbody.appendChild(row);
            });
        }
    }
}

// Skript automatisch starten
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        // Wir warten ganz kurz, falls andere Skripte Tabellen umschalten
        setTimeout(() => new TableSorter(), 100);
    });
}
