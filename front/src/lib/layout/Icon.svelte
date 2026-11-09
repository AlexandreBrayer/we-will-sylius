<script lang="ts" module>
	// Pictos Tabler (grille de 24, au trait) : ceux que la maquette de Quentin ne dessine pas.
	const STROKE = {
		bag: [
			'M6.331 8H17.67a2 2 0 0 1 1.977 2.304l-1.255 8.152A3 3 0 0 1 15.426 21H8.574a3 3 0 0 1-2.965-2.544l-1.255-8.152A2 2 0 0 1 6.331 8',
			'M9 11V6a3 3 0 0 1 6 0v5'
		],
		chevron: ['m6 9l6 6l6-6'],
		close: ['M18 6L6 18M6 6l12 12'],
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
		camera: [
			'M5 7h1a2 2 0 0 0 2-2a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1a2 2 0 0 0 2 2h1a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2',
			'M9 13a3 3 0 1 0 6 0a3 3 0 0 0-6 0'
		]
	} as const;

	// Pictos de la maquette de Quentin (Figma « YACK Seduction », fichier dkxHnGYFPmmhL1U0szWozK) : pleins,
	// exportés du fichier en SVG puis optimisés (svgo, 2 décimales), une couleur (currentColor). La boîte (viewBox)
	// est celle du cadre Figma : le picto garde ses proportions et ses marges à toutes les tailles.
	const FILLED = {
		// Nœud 7:616 (« Ajouter au panier », pastille de la carte produit), dessiné pour 16 px
		cart: { viewBox: '0 0 16 16', d: 'M3.06 14.72q-.4-.39-.4-.94t.4-.94.94-.4.94.4.4.94-.4.94q-.39.4-.94.4t-.94-.4m8 0q-.4-.39-.4-.94t.4-.94.94-.4.94.4.4.94-.4.94-.94.4-.94-.4M3.8 4l1.53 3.56h5.58L12.43 4zm-.57-1.33h10.55q.26 0 .38.2.13.2.03.43l-2.07 4.77q-.15.38-.48.6-.3.21-.73.22H4.98l-.78 1.33h8.47q.28 0 .47.19.18.18.2.48a.6.6 0 0 1-.2.48.7.7 0 0 1-.47.19H4.22q-.8 0-1.17-.67a1.3 1.3 0 0 1 0-1.33l.97-1.67-2.43-5.67H.67a.6.6 0 0 1-.47-.18.7.7 0 0 1-.2-.48q0-.3.2-.48t.47-.2h1.35a.6.6 0 0 1 .36.12q.16.12.25.3z' },
		// Nœud 4:51 (loupe de l'en-tête) ; 6:467 et 7:1223 (pastille « Recherche ») en sont la réduction à 16 px
		search: { viewBox: '0 0 24 24', d: 'M9.5 16a6.3 6.3 0 0 1-4.61-1.89A6.3 6.3 0 0 1 3 9.5q0-2.73 1.89-4.61A6.3 6.3 0 0 1 9.5 3q2.72 0 4.61 1.89A6.3 6.3 0 0 1 16 9.5a6 6 0 0 1-1.3 3.8l5.6 5.6q.28.28.28.7t-.28.7a1 1 0 0 1-.7.27 1 1 0 0 1-.7-.27l-5.6-5.6A6 6 0 0 1 9.5 16m0-2q1.88 0 3.19-1.31A4.3 4.3 0 0 0 14 9.5q0-1.88-1.31-3.19A4.3 4.3 0 0 0 9.5 5Q7.62 5 6.31 6.31A4.3 4.3 0 0 0 5 9.5q0 1.88 1.31 3.19A4.3 4.3 0 0 0 9.5 14' },
		// Nœud 4:47 (pastille « Nos produits »), dessiné pour 16 px
		menu: { viewBox: '0 0 16 16', d: 'M2.44 12a.6.6 0 0 1-.47-.19.7.7 0 0 1-.2-.48q0-.3.2-.48t.47-.18h11.12q.28 0 .47.18.18.2.2.48a.6.6 0 0 1-.2.48.7.7 0 0 1-.47.19zm0-3.33a.6.6 0 0 1-.47-.19.7.7 0 0 1-.2-.48q0-.3.2-.47t.47-.2h11.12q.28 0 .47.19.18.18.2.48a.6.6 0 0 1-.2.48.7.7 0 0 1-.47.2zm0-3.34a.6.6 0 0 1-.47-.18.7.7 0 0 1-.2-.48q0-.3.2-.47.21-.18.47-.2h11.12q.28 0 .47.19.18.18.2.48 0 .3-.2.47a1 1 0 0 1-.47.2z' },
		// Nœud 4:68 (« Connexion / Inscription », bandeau supérieur)
		user: { viewBox: '0 0 24 24', d: 'M9.18 10.83A4 4 0 0 1 8 8q0-1.65 1.18-2.82A4 4 0 0 1 12 4q1.65 0 2.83 1.18Q16.03 6.38 16 8a4 4 0 0 1-1.17 2.82A3.8 3.8 0 0 1 12 12q-1.68 0-2.82-1.17M4 18v-.8q0-.86.44-1.56t1.16-1.09A14.4 14.4 0 0 1 12 13q1.65 0 3.25.39 1.6.4 3.15 1.16A3 3 0 0 1 20 17.2v.8q0 .83-.59 1.41-.6.6-1.41.59H6q-.82 0-1.41-.59-.6-.6-.59-1.41m2 0h12v-.8a1 1 0 0 0-.14-.5q-.14-.22-.36-.35A12.6 12.6 0 0 0 12 15q-1.4-.01-2.77.34-1.36.35-2.73 1.01-.23.13-.36.35a1 1 0 0 0-.14.5zm7.41-8.59q.6-.6.59-1.41 0-.82-.59-1.41-.6-.6-1.41-.59-.83 0-1.41.59-.6.6-.59 1.41 0 .82.59 1.41.6.6 1.41.59.83 0 1.41-.59' },
		// Nœud 4:83 (« Suivi de commandes », bandeau supérieur)
		truck: { viewBox: '0 0 24 24', d: 'M3.88 19.13A3 3 0 0 1 3 17H2a1 1 0 0 1-.71-.29A1 1 0 0 1 1 16V6q0-.82.59-1.41.6-.6 1.41-.59h12q.83 0 1.41.59.6.6.59 1.41v2h2a2 2 0 0 1 1.6.8l2.2 2.93a1 1 0 0 1 .2.6V16q0 .43-.29.71-.3.28-.71.29h-1q0 1.24-.88 2.13-.89.89-2.12.87-1.24 0-2.13-.88A2.8 2.8 0 0 1 15 17H9q0 1.24-.88 2.13-.89.89-2.12.87-1.24 0-2.12-.87m2.84-1.41A1 1 0 0 0 7 17q0-.43-.29-.71a1 1 0 0 0-1.42 0q-.3.3-.29.71 0 .42.29.71.3.3.71.29.42 0 .71-.29zM3 15h.8q.43-.45.98-.73.55-.29 1.22-.27a3 3 0 0 1 2.2 1H15V6H3zm15.71 2.71q.3-.28.29-.71 0-.43-.29-.71-.3-.28-.71-.29a1 1 0 0 0-.71.29q-.3.3-.29.71 0 .4.29.71.3.3.71.29.42 0 .71-.29M17 13h4.25L19 10h-2z' },
		// Nœud 4:76 (« Favoris », bandeau supérieur)
		heart: { viewBox: '0 0 24 24', d: 'M11.29 20.2a2 2 0 0 1-.64-.4l-1.72-1.57a69 69 0 0 1-4.79-4.82A7.7 7.7 0 0 1 2 8.15q0-2.36 1.58-3.93A5.3 5.3 0 0 1 7.5 2.65q1.33 0 2.5.56t2 1.54a6 6 0 0 1 4.5-2.1 5.35 5.35 0 0 1 5.5 5.5q0 2.88-2.13 5.28a62 62 0 0 1-4.82 4.83l-1.7 1.55q-.28.27-.64.4t-.71.12-.71-.13m-.24-13.45q-.72-1.03-1.55-1.56a3.6 3.6 0 0 0-2-.54A3.4 3.4 0 0 0 4 8.15q0 1.3.93 2.76t2.21 2.84 2.65 2.57q1.37 1.2 2.21 1.98.85-.78 2.21-1.98t2.65-2.57q1.3-1.37 2.21-2.84T20 8.15a3.4 3.4 0 0 0-3.5-3.5q-1.17 0-2 .54-.82.54-1.55 1.56-.17.26-.42.38-.25.13-.53.12-.28 0-.52-.12a1 1 0 0 1-.43-.38' },
		// Nœud 7:612 (sélecteur de quantité), dessiné pour 18 px
		plus: { viewBox: '0 0 18 18', d: 'M8.25 9.75h-3.5a.7.7 0 0 1-.53-.21A.8.8 0 0 1 4 9q0-.33.22-.53.23-.2.53-.22h3.5v-3.5q0-.32.21-.53A.8.8 0 0 1 9 4q.33 0 .54.22a.8.8 0 0 1 .22.53v3.5h3.5A.7.7 0 0 1 14 9q0 .33-.22.54a.8.8 0 0 1-.53.22h-3.5v3.5A.7.7 0 0 1 9 14a.7.7 0 0 1-.53-.22 1 1 0 0 1-.22-.53z' },
		// Nœud 7:608 (sélecteur de quantité), dessiné pour 18 px
		minus: { viewBox: '0 0 18 18', d: 'M4.58 9.75A.74.74 0 0 1 3.83 9q0-.33.22-.53t.53-.22h8.83q.3 0 .53.21.22.21.23.54 0 .33-.22.54a.8.8 0 0 1-.53.22z' },
		// Nœud 6:336 (chevron des accès rapides et des liens « Découvrir », 6 × 11 px) : la boîte de 24 le place
		// comme dans la ligne de texte de 24 px de la maquette (y = 6,5), centré en largeur.
		'chevron-right': { viewBox: '-9 -6.5 24 24', d: 'M3.82 5.5.26 1.65A1 1 0 0 1 0 .95 1 1 0 0 1 .26.28.8.8 0 0 1 .9 0q.39 0 .64.27l4.2 4.54q.14.14.2.32.04.18.06.37a1 1 0 0 1-.06.38 1 1 0 0 1-.2.31l-4.2 4.54A1 1 0 0 1 .9 11a1 1 0 0 1-.64-.27 1 1 0 0 1-.26-.69q0-.4.26-.69z' }
	} as const;

	export type IconName = keyof typeof STROKE | keyof typeof FILLED;

	const filled = (name: IconName) => (name in FILLED ? FILLED[name as keyof typeof FILLED] : null);
</script>

<script lang="ts">
	/** `size`: Sylius icon modifier (icon-sm, icon-md…). */
	let { name, size = '' }: { name: IconName; size?: '' | 'sm' | 'md' | 'lg' } = $props();
	const glyph = $derived(filled(name));
</script>

{#if glyph}
	<svg viewBox={glyph.viewBox} class="icon" class:icon-sm={size === 'sm'} class:icon-md={size === 'md'} class:icon-lg={size === 'lg'} height="1em" width="1em" aria-hidden="true">
		<g fill="currentColor"><path d={glyph.d} /></g>
	</svg>
{:else}
	<svg viewBox="0 0 24 24" class="icon" class:icon-sm={size === 'sm'} class:icon-md={size === 'md'} class:icon-lg={size === 'lg'} height="1em" width="1em" aria-hidden="true">
		<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
			{#each STROKE[name as keyof typeof STROKE] as d (d)}<path {d} />{/each}
		</g>
	</svg>
{/if}
