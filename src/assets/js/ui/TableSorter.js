/**
 * Engine für clientseitige interaktive Tabellensortierungen.
 * Nutzt den Schwartzian Transform (Data-Caching) für O(n) DOM-Lesezugriffe,
 * um Performance-Engpässe (Layout Thrashing) bei großen Tabellen zu vermeiden.
 */
export class TableSorter {
    constructor(tableElement) {
        this.table = tableElement;
        this.tbody = this.table.querySelector('tbody');
        this.headers = this.table.querySelectorAll('th.js-sort-header');

        // Zentraler Controller für restlose Garbage Collection
        this.abortController = new AbortController();

        this.init();
    }

    init() {
        // Idempotentes Mounting - Schützt vor doppelter Event-Bindung bei AJAX Tab-Wechseln
        if (this.table.dataset.sorterBound === 'true') return;
        this.table.dataset.sorterBound = 'true';

        // Wenn die Tabelle leer ist (Meldung über colspan), nicht sortieren
        if (!this.tbody || this.headers.length === 0 || this.tbody.querySelector('td[colspan]')) {
            return;
        }

        // Originale Reihenfolge für den "Reset" (3. Klick) speichern
        this.originalRows = Array.from(this.tbody.querySelectorAll('tr'));

        const options = { signal: this.abortController.signal };

        this.headers.forEach((th, index) => {
            th.classList.add('is-sortable');
            th.title = 'Klicken zum Sortieren';

            // Non-destruktiver Insert verhindert das Löschen von Child-Event-Listenern
            th.insertAdjacentHTML('beforeend', ' <span class="c-sort-icon">⇅</span>');

            th.addEventListener('click', () => this.sortTable(th, index), options);
        });
    }

    sortTable(th, columnIndex) {
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

        // DocumentFragment verhindert hunderte Reflows/Repaints beim Rendern!
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

            // --- 1. MAP: Schwartzian Transform (O(n) DOM Reads) ---
            const isLikelyNumber = (str) => /^[-0-9., €]+$/.test(str.trim());

            const mappedRows = this.originalRows.map((row) => {
                const cell = row.children[columnIndex];
                let rawValue = '';

                if (cell) {
                    // Null-Coalescing: Bevorzuge data-sort-val (z.B. für ISO-Datum), sonst den sichtbaren Text
                    rawValue = cell.getAttribute('data-sort-val') ?? cell.innerText.trim();
                }

                let sortValue = rawValue;
                let isNumeric = false;

                if (isLikelyNumber(rawValue)) {
                    const cleanStr = rawValue.replace(/[^0-9,-]+/g, '').replace(',', '.');
                    if (cleanStr !== '') {
                        sortValue = Number(cleanStr);
                        isNumeric = Number.isFinite(sortValue);
                    }
                }

                // Fallback: String Sortierung (Case Insensitive)
                if (!isNumeric) {
                    sortValue = rawValue.toLowerCase();
                }

                return {
                    row: row,
                    value: sortValue,
                    isNumeric: isNumeric,
                };
            });

            // --- 2. SORT: Schwartzian Transform (O(n log n) RAM-Zugriffe, 0 DOM-Zugriffe!) ---
            mappedRows.sort((a, b) => {
                // Wenn beide Werte saubere Zahlen sind, numerisch sortieren
                if (a.isNumeric && b.isNumeric) {
                    return nextSort === 'asc' ? a.value - b.value : b.value - a.value;
                }

                // Fallback: String Sortierung
                const strA = String(a.value);
                const strB = String(b.value);

                if (strA < strB) return nextSort === 'asc' ? -1 : 1;
                if (strA > strB) return nextSort === 'asc' ? 1 : -1;
                return 0;
            });

            // --- 3. UNMAP: Sortierte Zeilen in das Fragment einfügen ---
            mappedRows.forEach((mapped) => {
                fragment.appendChild(mapped.row);
            });
        }

        // Mit nur einem einzigen DOM-Insert die gesamte Tabelle neu rendern
        this.tbody.appendChild(fragment);
    }

    /**
     * Wird vom Bootstrapper aufgerufen, wenn das Element aus dem DOM entfernt wird.
     */
    destroy() {
        this.abortController.abort();
    }
}
