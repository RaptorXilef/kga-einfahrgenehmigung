/**
 * Initialisiert Chart.js für die Jahres-/Monatsstatistiken und steuert das Toggling.
 */
export class DashboardStats {
    constructor(container) {
        this.container = container;
        this.canvas = this.container.querySelector('#yearlyStatsChart');
        this.btnMonth = this.container.querySelector('#chartToggleMonth');
        this.btnYear = this.container.querySelector('#chartToggleYear');

        const dataScript = this.container.querySelector('#chart-data');
        this.chartData = dataScript ? JSON.parse(dataScript.textContent || '{}') : null;

        if (this.canvas && this.chartData && typeof window.Chart !== 'undefined') {
            this.init();
        }
    }

    init() {
        // Chart initialisieren (Standardmäßig Monatlich)
        this.currentChart = new window.Chart(this.canvas, {
            type: 'bar',
            data: {
                labels: this.chartData.monthLabels,
                datasets: [
                    {
                        label: 'Umsatz Soll (€)',
                        data: this.chartData.monthRevenue,
                        backgroundColor: 'rgba(52, 152, 219, 0.6)',
                        borderColor: '#3498db',
                        borderWidth: 2,
                        borderRadius: 5,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Anzahl Genehmigungen',
                        data: this.chartData.monthCounts,
                        borderColor: '#6366f1',
                        backgroundColor: '#6366f1',
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
                                    label += context.raw + ' Stück';
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
                        grid: { color: '#f1f5f9' },
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

        // Event Listeners für die Toggle-Buttons
        if (this.btnMonth && this.btnYear) {
            this.btnMonth.addEventListener('click', (e) => {
                e.preventDefault();
                this.btnMonth.classList.replace('c-button--secondary', 'c-button--primary');
                this.btnYear.classList.replace('c-button--primary', 'c-button--secondary');

                this.currentChart.data.labels = this.chartData.monthLabels;
                this.currentChart.data.datasets[0].data = this.chartData.monthRevenue;
                this.currentChart.data.datasets[1].data = this.chartData.monthCounts;
                this.currentChart.update();
            });

            this.btnYear.addEventListener('click', (e) => {
                e.preventDefault();
                this.btnYear.classList.replace('c-button--secondary', 'c-button--primary');
                this.btnMonth.classList.replace('c-button--primary', 'c-button--secondary');

                this.currentChart.data.labels = this.chartData.yearLabels;
                this.currentChart.data.datasets[0].data = this.chartData.yearRevenue;
                this.currentChart.data.datasets[1].data = this.chartData.yearCounts;
                this.currentChart.update();
            });
        }
    }
}
