/**
 * Engine für clientseitige interaktive Tabellensortierungen.
 * Scannt strukturierte Klassen (`.js-sort-table`), injiziert grafische Sortierungs-Indikatoren (⇅, ↓, ↑),
 * speichert die Standard-Reihenfolge (`originalRows`) für Resets und sortiert Spalten dynamisch.
 */
export class TableSorter {
    constructor(tableElement) {
        this.table = tableElement;
        this.tbody = this.table.querySelector('tbody');
        this.headers = this.table.querySelectorAll('th.js-sort-header');

        this.init();
    }

    init() {
        // Wenn die Tabelle leer ist (Meldung über colspan), nicht sortieren
        if (!this.tbody || this.headers.length === 0 || this.tbody.querySelector('td[colspan]'))
            return;

        // Originale Reihenfolge für den "Reset" (3. Klick) speichern
        this.originalRows = Array.from(this.tbody.querySelectorAll('tr'));

        this.headers.forEach((th, index) => {
            th.style.cursor = 'pointer';
            th.style.userSelect = 'none';
            th.title = 'Klicken zum Sortieren';

            // Non-destruktiver Insert verhindert das Löschen von Child-Event-Listenern
            th.insertAdjacentHTML(
                'beforeend',
                ' <span class="sort-icon" style="opacity:0.3; font-size:1em; margin-left: 4px; display:inline-block; vertical-align:middle;">⇅</span>'
            );

            th.addEventListener('click', () => this.sortTable(th, index));
        });
    }

    sortTable(th, columnIndex) {
        const rows = Array.from(this.tbody.querySelectorAll('tr'));

        // Aktuellen Status ermitteln
        const currentSort = th.getAttribute('data-sort-dir') || 'none';
        let nextSort = 'asc';

        if (currentSort === 'asc') nextSort = 'desc';
        else if (currentSort === 'desc') nextSort = 'none';

        // Alle Icons & Stati zurücksetzen
        this.headers.forEach((header) => {
            header.setAttribute('data-sort-dir', 'none');
            const icon = header.querySelector('.sort-icon');
            if (icon) {
                icon.innerHTML = '⇅';
                icon.style.opacity = '0.3';
                icon.style.color = 'inherit';
            }
        });

        // DocumentFragment verhindert 1000x Reflows/Repaints beim Rendern!
        const fragment = document.createDocumentFragment();

        if (nextSort === 'none') {
            // 3. Klick: Originalzustand wiederherstellen
            this.originalRows.forEach((row) => {
                fragment.appendChild(row);
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

                // Deutsches Zahlenformat (z.B. "15,00 €") korrekt für isFinite vorbereiten
                const parseGermanNumber = (str) => {
                    if (!str) return NaN;
                    const cleanStr = str.replace(/[^0-9,-]+/g, '').replace(',', '.');
                    return cleanStr === '' ? NaN : Number(cleanStr);
                };

                const numA = parseGermanNumber(valA);
                const numB = parseGermanNumber(valB);

                if (
                    valA.trim() !== '' &&
                    valB.trim() !== '' &&
                    Number.isFinite(numA) &&
                    Number.isFinite(numB)
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
                fragment.appendChild(row);
            });
        }

        // Mit nur einem einzigen DOM-Insert die gesamte Tabelle neu rendern
        this.tbody.appendChild(fragment);
    }
}
