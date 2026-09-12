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
        // Idempotentes Mounting - Schützt vor doppelter Event-Bindung bei AJAX Tab-Wechseln
        if (this.table.dataset.sorterBound === 'true') return;
        this.table.dataset.sorterBound = 'true';

        // Wenn die Tabelle leer ist (Meldung über colspan), nicht sortieren
        if (!this.tbody || this.headers.length === 0 || this.tbody.querySelector('td[colspan]'))
            return;

        // Originale Reihenfolge für den "Reset" (3. Klick) speichern
        this.originalRows = Array.from(this.tbody.querySelectorAll('tr'));

        this.headers.forEach((th, index) => {
            th.classList.add('is-sortable');
            th.title = 'Klicken zum Sortieren';

            // Non-destruktiver Insert verhindert das Löschen von Child-Event-Listenern
            th.insertAdjacentHTML('beforeend', ' <span class="c-sort-icon">⇅</span>');

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
            const icon = header.querySelector('.c-sort-icon');
            if (icon) icon.innerHTML = '⇅';
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
            const icon = th.querySelector('.c-sort-icon');
            if (icon) {
                icon.innerHTML = nextSort === 'asc' ? '↓' : '↑';
            }

            rows.sort((a, b) => {
                // O(1) Property Access anstelle von extrem teurem O(n) DOM Query!
                const cellA = a.children[columnIndex];
                const cellB = b.children[columnIndex];

                if (!cellA || !cellB) return 0;

                // Null-Coalescing: Bevorzuge data-sort-val (z.B. für ISO-Datum), sonst den sichtbaren Text
                let valA = cellA.getAttribute('data-sort-val') ?? cellA.innerText.trim();
                let valB = cellB.getAttribute('data-sort-val') ?? cellB.innerText.trim();

                // String-Erkennung. Ein String ist nur eine Zahl, wenn er keine normalen Buchstaben enthält
                const isLikelyNumber = (str) => /^[-0-9., €]+$/.test(str.trim());

                let numA = NaN;
                let numB = NaN;

                if (isLikelyNumber(valA)) {
                    const cleanStr = valA.replace(/[^0-9,-]+/g, '').replace(',', '.');
                    if (cleanStr !== '') numA = Number(cleanStr);
                }

                if (isLikelyNumber(valB)) {
                    const cleanStr = valB.replace(/[^0-9,-]+/g, '').replace(',', '.');
                    if (cleanStr !== '') numB = Number(cleanStr);
                }

                // Wenn beide Werte saubere Zahlen sind, numerisch sortieren
                if (Number.isFinite(numA) && Number.isFinite(numB)) {
                    return nextSort === 'asc' ? numA - numB : numB - numA;
                }

                // Fallback: String Sortierung (Case Insensitive)
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
