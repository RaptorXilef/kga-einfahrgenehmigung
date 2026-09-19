export class BankImport {
    constructor(container) {
        this.container = container;
        this.selectors = this.container.querySelectorAll('.js-bank-selector');
        this.abortController = new AbortController();

        const dataScript = this.container.querySelector('.js-bank-preview-data');

        try {
            this.rowData = dataScript ? JSON.parse(dataScript.textContent || '[]') : [];
        } catch (error) {
            console.error('[BankImport] Fataler Fehler beim Parsen.', error);
            this.rowData = [];
        }

        if (this.selectors.length > 0 && this.rowData.length > 0) {
            this.init();
        }
    }

    init() {
        const options = { signal: this.abortController.signal };

        this.selectors.forEach((select) => {
            select.addEventListener('change', (e) => this.updatePreview(e.target), options);
            this.updatePreview(select);
        });
    }

    updatePreview(selectElement) {
        const selectedIndex = parseInt(selectElement.value, 10);
        const targetClass = selectElement.getAttribute('data-preview-target');
        const targetDisplay = this.container.querySelector(`.${targetClass}`);

        if (targetDisplay) {
            const value =
                this.rowData[selectedIndex] !== undefined && this.rowData[selectedIndex] !== ''
                    ? this.rowData[selectedIndex]
                    : '[LEER]';
            // Reflows vermeiden durch textContent statt innerText
            targetDisplay.textContent = value;
        }
    }

    destroy() {
        this.abortController.abort();
    }
}
