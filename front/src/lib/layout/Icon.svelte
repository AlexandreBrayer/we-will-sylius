<script lang="ts" module>
	// Tabler icons (24×24 stroke style) used by the Sylius shop templates and the shop chrome.
	const ICONS = {
		user: ['M8 7a4 4 0 1 0 8 0a4 4 0 0 0-8 0M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2'],
		bag: [
			'M6.331 8H17.67a2 2 0 0 1 1.977 2.304l-1.255 8.152A3 3 0 0 1 15.426 21H8.574a3 3 0 0 1-2.965-2.544l-1.255-8.152A2 2 0 0 1 6.331 8',
			'M9 11V6a3 3 0 0 1 6 0v5'
		],
		truck: [
			'M5 17a2 2 0 1 0 4 0a2 2 0 1 0-4 0m10 0a2 2 0 1 0 4 0a2 2 0 1 0-4 0',
			'M5 17H3v-4M2 5h11v12m-4 0h6m4 0h2v-6h-8m0-5h5l3 5M3 9h4'
		],
		chevron: ['m6 9l6 6l6-6'],
		'chevron-right': ['m9 6l6 6l-6 6'],
		menu: ['M4 6h16M4 12h16M4 18h16'],
		search: ['M3 10a7 7 0 1 0 14 0a7 7 0 1 0-14 0m18 11l-6-6'],
		close: ['M18 6L6 18M6 6l12 12'],
		// Icons of the commercial demo (Quentin's Figma): heart, cart, document, part, location pin…
		heart: ['M19.5 12.572L12 20l-7.5-7.428A5 5 0 1 1 12 6.006a5 5 0 1 1 7.5 6.572'],
		cart: ['M4 19a2 2 0 1 0 4 0a2 2 0 1 0-4 0m11 0a2 2 0 1 0 4 0a2 2 0 1 0-4 0', 'M17 17H6V3H4', 'm6 5l14 1l-1 7H6'],
		document: [
			'M14 3v4a1 1 0 0 0 1 1h4',
			'M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2',
			'M9 9h1m-1 4h6m-6 4h6'
		],
		part: ['M7 10h3V7L6.5 3.5a6 6 0 0 1 8 8l6 6a2 2 0 0 1-3 3l-6-6a6 6 0 0 1-8-8L7 7z'],
		pin: ['M9 11a3 3 0 1 0 6 0a3 3 0 0 0-6 0', 'M17.657 16.657l-4.243 4.243a2 2 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0'],
		home: ['M5 12H3l9-9l9 9h-2M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7', 'M9 21v-6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v6'],
		grid: [
			'M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1zm10 0a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1zM4 15a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1zm10 0a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1z'
		],
		check: ['m5 12l5 5L20 7'],
		plus: ['M12 5v14m-7-7h14'],
		minus: ['M5 12h14']
	} as const;

	export type IconName = keyof typeof ICONS;
</script>

<script lang="ts">
	/** `size`: Sylius icon modifier (icon-sm, icon-md…). */
	let { name, size = '' }: { name: IconName; size?: '' | 'sm' | 'md' | 'lg' } = $props();
</script>

<svg viewBox="0 0 24 24" class="icon" class:icon-sm={size === 'sm'} class:icon-md={size === 'md'} class:icon-lg={size === 'lg'} height="1em" width="1em" aria-hidden="true">
	<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
		{#each ICONS[name] as d (d)}<path {d} />{/each}
	</g>
</svg>
