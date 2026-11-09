<script lang="ts" module>
	// Leaflet is loaded from the unpkg CDN on demand, as on the Twig page (POC: no npm dependency).
	const LEAFLET_VERSION = '1.9.4';

	type LatLng = [number, number];
	interface LeafletLayer {
		addTo(target: LeafletMap | LeafletGroup): this;
	}
	interface LeafletMarker extends LeafletLayer {
		bindPopup(html: string): this;
		on(event: 'mouseover' | 'mouseout', handler: () => void): this;
		getElement(): HTMLElement | undefined;
		setZIndexOffset(offset: number): this;
	}
	interface LeafletIcon {
		readonly options: object;
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
		marker(position: LatLng, options: { title: string; alt: string; icon: LeafletIcon; keyboard: boolean }): LeafletMarker;
		divIcon(options: { className: string; html: string; iconSize: [number, number]; iconAnchor: [number, number]; popupAnchor: [number, number] }): LeafletIcon;
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
	// Map of the installers listed (port of the installer-map Stimulus controller): magenta pins numbered
	// like the list; `active` (bindable) is the installer highlighted on both sides.
	import type { Installer } from './types.js';

	let { installers, active = $bindable(null) }: { installers: Installer[]; active?: number | null } = $props();

	/** Pins of the current list, in the order of `installers`. */
	let pins: LeafletMarker[] = [];

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
		pins = [];
		const points = installers.map((installer, index): LatLng => {
			const position: LatLng = [installer.latitude, installer.longitude];
			const icon = leaflet!.divIcon({
				className: 'yack-map-pin',
				html: `<span class="yack-marker">${index + 1}</span>`,
				iconSize: [30, 30],
				iconAnchor: [15, 15],
				popupAnchor: [0, -15]
			});
			const pin = leaflet!
				.marker(position, { title: installer.name, alt: `${index + 1}. ${installer.name}`, icon, keyboard: true })
				.bindPopup(`<strong>${escape(installer.name)}</strong><br>${escape(installer.postcode)} ${escape(installer.city)}`)
				.on('mouseover', () => (active = index))
				.on('mouseout', () => (active = null))
				.addTo(markers!);
			pins.push(pin);
			return position;
		});
		if (points.length > 0) map.fitBounds(points, { padding: [30, 30], maxZoom: 10 });
		else map.setView([46.6, 2.4], 5);
	});

	// Highlight of the pin of the installer hovered in the list (and of the pin hovered itself).
	$effect(() => {
		if (!ready) return;
		void installers;
		pins.forEach((pin, index) => {
			const on = index === active;
			pin.getElement()?.classList.toggle('is-active', on);
			pin.setZIndexOffset(on ? 1000 : 0);
		});
	});
</script>

<div class="yack-map" bind:this={element} data-test-yack-installer-map>
	{#if failed}<p class="p-3 text-body-secondary">Carte indisponible (CDN Leaflet injoignable).</p>{/if}
</div>
