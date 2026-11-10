// POC Yack : graphique du tableau de bord des synchronisations PIM/ERP (Catalogue > Synchronisation PIM/ERP).
// Synchronisations de chaque jour par statut (colonnes empilées) et produits écrits (courbe, axe de droite),
// lus dans les attributs data-* posés par admin/yack/catalog_sync/index.html.twig.
import ApexCharts from 'apexcharts';

const element = document.querySelector('[data-yack-sync-chart]');

if (element) {
    const { days, series } = JSON.parse(element.dataset.yackSyncChart);
    const atLeastOne = (max) => Math.max(max, 1);
    const runAxis = { seriesName: series[0].name, show: false, min: 0, max: atLeastOne, forceNiceScale: true, labels: { formatter: (value) => Math.round(value) } };

    new ApexCharts(element, {
        chart: { height: 300, stacked: true, toolbar: { show: false }, fontFamily: 'inherit' },
        colors: ['#2fb344', '#f59f00', '#d63939', '#a33c87'],
        series,
        stroke: { width: [0, 0, 0, 3], curve: 'smooth' },
        plotOptions: { bar: { columnWidth: '60%', borderRadius: 2 } },
        dataLabels: { enabled: false },
        legend: { position: 'top', horizontalAlign: 'left' },
        xaxis: { categories: days, tickAmount: 10, labels: { rotate: 0, hideOverlappingLabels: true } },
        yaxis: [
            { ...runAxis, show: true, title: { text: 'Synchronisations' } },
            runAxis,
            runAxis,
            { opposite: true, min: 0, max: atLeastOne, forceNiceScale: true, title: { text: 'Produits écrits' }, labels: { formatter: (value) => Math.round(value) } },
        ],
        tooltip: { shared: true, intersect: false },
    }).render();
}
