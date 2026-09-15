/**
 * Initialisiert Chart.js für die Jahres-/Monatsstatistiken und steuert das Toggling.
 */
export class DashboardStats {
    constructor(container) {
        this.container = container;

        // FIX: Strikte JS Hooks anstatt harter IDs
        this.canvas = this.container.querySelector('.js-yearly-stats-chart');
        this.btnMonth = this.container.querySelector('.js-chart-toggle-month');
        this.btnYear = this.container.querySelector('.js-chart-toggle-year');

        this.abortController = new AbortController();

        const dataScript = this.container.querySelector('#chart-data');

        try {
            this.chartData = dataScript ? JSON.parse(dataScript.textContent || '{}') : null;
        } catch {
            this.chartData = null;
        }

        if (this.canvas && this.chartData && typeof window.Chart !== 'undefined') {
            this.init();
        }
    }

    init() {
        // Aktive Chart-Instanz zwingend zerstören, um Ghosting/Memory Leaks bei Neuladen zu verhindern
        if (this.currentChart instanceof window.Chart) {
            this.currentChart.destroy();
        }

        // Chart initialisieren (Standardmäßig Monatlich)
        this.currentChart = new window.Chart(this.canvas, {
            type: 'bar',
            data: {
                labels: this.chartData.monthLabels,
                datasets: [
                    {
                        label: 'Umsatz Soll (€)',
                        data: this.chartData.monthRevenue,
                        backgroundColor: 'var(--color-primary-soft, rgba(52, 152, 219, 0.6))',
                        borderColor: 'var(--color-primary, #3498db)',
                        borderWidth: 2,
                        borderRadius: 5,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Anzahl Genehmigungen',
                        data: this.chartData.monthCounts,
                        borderColor: 'var(--color-success, #6366f1)',
                        backgroundColor: 'var(--color-success, #6366f1)',
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
                plugins: {
                    legend: { position: 'bottom' },
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
                    y: {
                        type: 'linear',
                        position: 'left',
                        grid: { color: 'var(--color-border)' },
                        title: { display: true, text: 'Euro (€)', font: { weight: 'bold' } },
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Menge', font: { weight: 'bold' } },
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
