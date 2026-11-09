/*
 * Graphique des ventes du tableau de bord, aux couleurs de la charte (maquette « Admin Sylius », Figma YACK Seduction).
 *
 * Remplace vendor/sylius/.../Resources/assets/scripts/statistics_chart.js au build (NormalModuleReplacementPlugin dans
 * webpack.config.js) : mêmes données (data-intervals, data-sales, data-paid-orders-count, data-currency), mêmes re-rendus
 * quand la période change. Les couleurs viennent des variables CSS --yack-chart-* (assets/admin/styles/_base.scss),
 * donc des tokens de la boutique ; les libellés des séries de data-yack-sales-label et data-yack-orders-label.
 */
import ApexCharts from 'apexcharts';

const SELECTOR = '#statistics-chart';

let chart = null;

const cssVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

// <html lang> vaut « fr_FR » : Intl veut « fr-FR », et on retombe sur le français si la locale est inconnue.
function locale() {
    const lang = (document.documentElement.lang || 'fr-FR').replace('_', '-');

    return Intl.NumberFormat.supportedLocalesOf(lang).length > 0 ? lang : 'fr-FR';
}

// « 2026-03 » devient « Mars » (période d'un an), « 2026-03-14 » devient « 14 mars » (deux semaines, mois), comme la maquette.
function intervalLabel(value, lang) {
    const match = /^(\d{4})-(\d{2})(?:-(\d{2}))?$/.exec(value);

    if (!match) {
        return value;
    }

    const [, year, month, day] = match;
    const date = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day ?? 1)));
    const label = new Intl.DateTimeFormat(lang, day ? { day: 'numeric', month: 'short', timeZone: 'UTC' } : { month: 'long', timeZone: 'UTC' }).format(date);

    return label.charAt(0).toUpperCase() + label.slice(1);
}

function renderChart() {
    const element = document.querySelector(SELECTOR);

    if (!element) {
        return;
    }

    const { currency = '', yackSalesLabel = 'Sales', yackOrdersLabel = 'Paid orders count' } = element.dataset;
    const fontFamily = cssVar('--yack-font');
    const text = cssVar('--yack-chart-text');
    const lang = locale();
    const format = new Intl.NumberFormat(lang, { maximumFractionDigits: 0 });
    const labels = { style: { colors: text, fontFamily, fontSize: '12px', fontWeight: 400 } };

    const options = {
        colors: [cssVar('--yack-chart-sales'), cssVar('--yack-chart-orders')],
        series: [
            { name: yackSalesLabel, data: JSON.parse(element.dataset.sales) },
            { name: yackOrdersLabel, data: JSON.parse(element.dataset.paidOrdersCount) },
        ],
        chart: {
            fontFamily,
            height: 340,
            toolbar: { show: false },
            type: 'line',
            zoom: { enabled: false },
        },
        stroke: { curve: 'straight', width: 3 },
        markers: { size: 0, hover: { size: 5 } },
        grid: {
            borderColor: cssVar('--yack-chart-grid'),
            strokeDashArray: 0,
            xaxis: { lines: { show: false } },
        },
        legend: {
            fontFamily,
            horizontalAlign: 'center',
            markers: { height: 0, width: 0 },
            position: 'bottom',
            show: true,
        },
        tooltip: { shared: true, style: { fontFamily } },
        xaxis: {
            axisBorder: { show: false },
            axisTicks: { show: false },
            categories: JSON.parse(element.dataset.intervals),
            labels: { ...labels, formatter: (value) => intervalLabel(String(value), lang) },
            position: 'top',
            tooltip: { enabled: false },
        },
        yaxis: [
            {
                axisBorder: { show: false },
                axisTicks: { show: false },
                forceNiceScale: true,
                labels: { ...labels, formatter: (value) => `${format.format(value)} ${currency}` },
                min: 0,
            },
            {
                axisBorder: { show: false },
                axisTicks: { show: false },
                forceNiceScale: true,
                labels: { ...labels, formatter: (value) => format.format(value) },
                min: 0,
                opposite: true,
            },
        ],
    };

    chart = new ApexCharts(element, options);
    chart.render();
}

renderChart();

const element = document.querySelector(SELECTOR);

if (element) {
    // Le composant Live met à jour les data-* quand la période change : le graphique est redessiné avec les nouvelles données.
    new MutationObserver((mutations) => {
        if (mutations.some(({ attributeName }) => attributeName === 'data-sales' || attributeName === 'data-intervals')) {
            chart?.destroy();
            renderChart();
        }
    }).observe(element, { attributes: true });
}
