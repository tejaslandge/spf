import ApexCharts from 'apexcharts';

window.ApexCharts = ApexCharts;

const FALLBACK_PALETTE = ['#2563eb', '#0ea5e9', '#10b981', '#f59e0b', '#f43f5e', '#8b5cf6'];

function initCharts(root = document) {
    root.querySelectorAll('[data-chart]').forEach((element) => {
        if (element.dataset.chartReady === 'true') {
            return;
        }

        try {
            const config = JSON.parse(element.dataset.chart);
            config.colors ??= FALLBACK_PALETTE;
            config.chart = {
                fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
                toolbar: { show: false },
                ...config.chart,
            };

            const chart = new ApexCharts(element, config);
            chart.render();
            element.dataset.chartReady = 'true';
        } catch (error) {
            console.error('Unable to render chart', error);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => initCharts());
document.addEventListener('livewire:navigated', () => initCharts());
