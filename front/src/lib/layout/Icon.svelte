<script lang="ts" module>
	// Tabler icons used by the Sylius shop templates (same paths, same 24×24 stroke style).
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
		menu: ['M4 6h16M4 12h16M4 18h16'],
		search: ['M3 10a7 7 0 1 0 14 0a7 7 0 1 0-14 0m18 11l-6-6'],
		close: ['M18 6L6 18M6 6l12 12']
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
