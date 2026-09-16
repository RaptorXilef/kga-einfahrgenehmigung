/**
 * Initialisiert Chart.js für die Jahres-/Monatsstatistiken und steuert das Toggling.
 * Nutzt CSS-Variablen (OKLCH Tokens) für nahtlosen Darkmode-Support (Stand 2026).
 */
export class DashboardStats {
    constructor(container) {
        this.container = container;

        this.canvas = this.container.querySelector('.js-yearly-stats-chart');
        this.btnMonth = this.container.querySelector('.js-chart-toggle-month');
        this.btnYear = this.container.querySelector('.js-chart-toggle-year');

        this.abortController = new AbortController();

        const dataScript = this.container.querySelector('.js-chart-data');

        try {
            this.chartData = dataScript ? JSON.parse(dataScript.textContent || '{}') : null;
        } catch {
            this.chartData = null;
        }

        if (this.canvas && this.chartData && typeof window.Chart !== 'undefined') {
            this.init();
        }
    }

    /**
     * Holt die aktuellen CSS-Tokens dynamisch aus dem Root-Element.
     */
    #getCssVariable(varName) {
        return getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
    }

    init() {
        // Aktive Chart-Instanz zwingend zerstören, um Ghosting/Memory Leaks bei Neuladen zu verhindern
        if (window.Chart) {
            const existingChart = window.Chart.getChart(this.canvas);
            if (existingChart) {
                existingChart.destroy();
            }
        }

        if (this.currentChart instanceof window.Chart) {
            this.currentChart.destroy();
        }

        // Dynamische OKLCH Tokens aus dem CSS auslesen! Keine harten HEX-Werte mehr!
        const colorPrimary = this.#getCssVariable('--color-primary');
        const colorPrimarySoft = this.#getCssVariable('--color-primary-soft');
        const colorSuccess = this.#getCssVariable('--color-success');
        const colorTextMain = this.#getCssVariable('--color-text-main');
        const colorBorder = this.#getCssVariable('--color-border');

        // Chart initialisieren (Standardmäßig Monatlich)
        this.currentChart = new window.Chart(this.canvas, {
            type: 'bar',
            data: {
                labels: this.chartData.monthLabels,
                datasets: [
                    {
                        label: 'Umsatz Soll (€)',
                        data: this.chartData.monthRevenue,
                        backgroundColor: colorPrimarySoft,
                        borderColor: colorPrimary,
                        borderWidth: 2,
                        borderRadius: 5,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Anzahl Genehmigungen',
                        data: this.chartData.monthCounts,
                        borderColor: colorSuccess,
                        backgroundColor: colorSuccess,
                        borderWidth: 3,
                        type: 'line',
                        tension: 0.3,
                        pointRadius: 5,
                        yAxisID: 'y1',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                color: colorTextMain, // Textfarbe dynamisch anpassen
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: colorTextMain },
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.datasetIndex === 0) {
                                    label += new Intl.NumberFormat('de-DE', {
                                        style: 'currency',
                                        currency: 'EUR',
                                    }).format(context.raw);
                                } else {
                                    label += `${context.raw} Stück`;
                                }
                                return label;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: { color: colorTextMain },
                        grid: { color: colorBorder },
                    },
                    y: {
                        type: 'linear',
                        position: 'left',
                        grid: { color: colorBorder },
                        ticks: { color: colorTextMain },
                        title: {
                            display: true,
                            text: 'Euro (€)',
                            color: colorTextMain,
                            font: { weight: 'bold' },
                        },
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: { color: colorTextMain },
                        title: {
                            display: true,
                            text: 'Menge',
                            color: colorTextMain,
                            font: { weight: 'bold' },
                        },
                    },
                },
            },
        });

        const options = { signal: this.abortController.signal };

        if (this.btnMonth && this.btnYear) {
            this.btnMonth.addEventListener(
                'click',
                (e) => {
                    e.preventDefault();
                    if (!this.currentChart || !this.chartData) return;

                    this.btnMonth.classList.replace('c-button--secondary', 'c-button--primary');
                    this.btnYear.classList.replace('c-button--primary', 'c-button--secondary');

                    this.currentChart.data.labels = this.chartData.monthLabels;
                    this.currentChart.data.datasets[0].data = this.chartData.monthRevenue;
                    this.currentChart.data.datasets[1].data = this.chartData.monthCounts;
                    this.currentChart.update();
                },
                options
            );

            this.btnYear.addEventListener(
                'click',
                (e) => {
                    e.preventDefault();
                    if (!this.currentChart || !this.chartData) return;

                    this.btnYear.classList.replace('c-button--secondary', 'c-button--primary');
                    this.btnMonth.classList.replace('c-button--primary', 'c-button--secondary');

                    this.currentChart.data.labels = this.chartData.yearLabels;
                    this.currentChart.data.datasets[0].data = this.chartData.yearRevenue;
                    this.currentChart.data.datasets[1].data = this.chartData.yearCounts;
                    this.currentChart.update();
                },
                options
            );
        }
    }

    destroy() {
        if (this.currentChart) {
            this.currentChart.destroy();
        }
        this.abortController.abort();
    }
}
