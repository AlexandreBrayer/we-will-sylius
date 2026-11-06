<script lang="ts" module>
	// Leaflet is loaded from the unpkg CDN on demand, as on the Twig page (POC: no npm dependency).
	const LEAFLET_VERSION = '1.9.4';

	type LatLng = [number, number];
	interface LeafletLayer {
		addTo(target: LeafletMap | LeafletGroup): this;
	}
	interface LeafletMarker extends LeafletLayer {
		bindPopup(html: string): this;
	}
	interface LeafletGroup extends LeafletLayer {
		clearLayers(): this;
	}
	interface LeafletMap {
		setView(center: LatLng, zoom: number): this;
		fitBounds(bounds: LatLng[], options: { padding: [number, number]; maxZoom: number }): this;
		remove(): void;
	}
	interface Leaflet {
		map(element: HTMLElement, options: { scrollWheelZoom: boolean }): LeafletMap;
		tileLayer(url: string, options: { maxZoom: number; attribution: string }): LeafletLayer;
		marker(position: LatLng, options: { title: string; alt: string }): LeafletMarker;
		layerGroup(): LeafletGroup;
	}

	let loading: Promise<Leaflet> | null = null;

	function loadLeaflet(): Promise<Leaflet> {
		const global = window as unknown as { L?: Leaflet };
		if (global.L) return Promise.resolve(global.L);

		loading ??= new Promise<Leaflet>((resolve, reject) => {
			const stylesheet = document.createElement('link');
			stylesheet.rel = 'stylesheet';
			stylesheet.href = `https://unpkg.com/leaflet@${LEAFLET_VERSION}/dist/leaflet.css`;
			document.head.appendChild(stylesheet);

			const script = document.createElement('script');
			script.src = `https://unpkg.com/leaflet@${LEAFLET_VERSION}/dist/leaflet.js`;
			script.onload = () => (global.L ? resolve(global.L) : reject(new Error('Leaflet')));
			script.onerror = () => {
				loading = null;
				script.remove();
				reject(new Error('Leaflet'));
			};
			document.head.appendChild(script);
		});
		return loading;
	}

	const escape = (text: string) =>
		text.replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char] ?? char);
</script>

<script lang="ts">
	// Map of the installers listed (port of the installer-map Stimulus controller).
	import type { Installer } from './types.js';

	let { installers }: { installers: Installer[] } = $props();

	let element: HTMLDivElement;
	let leaflet: Leaflet | null = null;
	let markers: LeafletGroup | null = null;
	let map: LeafletMap | null = null;
	let ready = $state(false);
	let failed = $state(false);

	$effect(() => {
		let disposed = false;
		loadLeaflet().then(
			(L) => {
				if (disposed) return;
				leaflet = L;
				map = L.map(element, { scrollWheelZoom: false }).setView([46.6, 2.4], 5);
				L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap' }).addTo(map);
				markers = L.layerGroup().addTo(map);
				ready = true;
			},
			() => (failed = true)
		);
		return () => {
			disposed = true;
			map?.remove();
			map = markers = leaflet = null;
			ready = false;
		};
	});

	// One marker per installer, refreshed when the filters change.
	$effect(() => {
		if (!ready || !leaflet || !markers || !map) return;
		markers.clearLayers();
		const points = installers.map((installer): LatLng => {
			const position: LatLng = [installer.latitude, installer.longitude];
			leaflet!
				.marker(position, { title: installer.name, alt: installer.name })
				.bindPopup(`<strong>${escape(installer.name)}</strong><br>${escape(installer.postcode)} ${escape(installer.city)}`)
				.addTo(markers!);
			return position;
		});
		if (points.length > 0) map.fitBounds(points, { padding: [30, 30], maxZoom: 10 });
		else map.setView([46.6, 2.4], 5);
	});
</script>

<div class="yack-map rounded-3 border" bind:this={element} data-test-yack-installer-map>
	{#if failed}<p class="p-3 text-body-secondary">Carte indisponible (CDN Leaflet injoignable).</p>{/if}
</div>
