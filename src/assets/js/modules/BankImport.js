/**
 * Logik für den Bank-CSV Import: Dynamische Vorschau der ausgewählten Spalten.
 */
export class BankImport {
    constructor(container) {
        this.container = container;
        this.selectors = this.container.querySelectorAll('.js-bank-selector');

        // JSON-Daten aus dem HTML-Template sicher auslesen
        const dataScript = this.container.querySelector('#bank-preview-data');
        this.rowData = dataScript ? JSON.parse(dataScript.textContent || '[]') : [];

        if (this.selectors.length > 0 && this.rowData.length > 0) {
            this.init();
        }
    }

    init() {
        this.selectors.forEach((select) => {
            select.addEventListener('change', (e) => this.updatePreview(e.target));
            this.updatePreview(select); // Initiale Vorschau laden
        });
    }

    updatePreview(selectElement) {
        const selectedIndex = parseInt(selectElement.value, 10);
        const targetId = selectElement.getAttribute('data-preview-target');
        const targetDisplay = document.getElementById(targetId);

        if (targetDisplay) {
            const value =
                this.rowData[selectedIndex] !== undefined && this.rowData[selectedIndex] !== ''
                    ? this.rowData[selectedIndex]
                    : '[LEER]';
            targetDisplay.innerText = value;
        }
    }
}
