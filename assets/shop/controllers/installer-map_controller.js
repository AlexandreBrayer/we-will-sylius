import { Controller } from '@hotwired/stimulus';

const LEAFLET_VERSION = '1.9.4';
let leafletLoading = null;

/* Leaflet is loaded from the unpkg CDN on demand (POC: no extra npm dependency). */
function loadLeaflet() {
    if (window.L) {
        return Promise.resolve(window.L);
    }

    leafletLoading ??= new Promise((resolve, reject) => {
        const stylesheet = document.createElement('link');
        stylesheet.rel = 'stylesheet';
        stylesheet.href = `https://unpkg.com/leaflet@${LEAFLET_VERSION}/dist/leaflet.css`;
        document.head.appendChild(stylesheet);

        const script = document.createElement('script');
        script.src = `https://unpkg.com/leaflet@${LEAFLET_VERSION}/dist/leaflet.js`;
        script.onload = () => resolve(window.L);
        script.onerror = reject;
        document.head.appendChild(script);
    });

    return leafletLoading;
}

export default class extends Controller {
    static values = { markers: Array };

    async connect() {
        try {
            const L = await loadLeaflet();
            this.map = L.map(this.element, { scrollWheelZoom: false }).setView([46.6, 2.4], 5);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; OpenStreetMap',
            }).addTo(this.map);

            const points = this.markersValue.map((marker) => {
                L.marker([marker.lat, marker.lng]).addTo(this.map).bindPopup(`<strong>${marker.name}</strong><br>${marker.city}`);
                return [marker.lat, marker.lng];
            });
            if (points.length > 0) {
                this.map.fitBounds(points, { padding: [30, 30], maxZoom: 10 });
            }
        } catch (error) {
            this.element.textContent = 'Carte indisponible (CDN Leaflet injoignable).';
        }
    }

    disconnect() {
        this.map?.remove();
    }
}
