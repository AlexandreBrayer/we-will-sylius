// Tabler Icons, same paths as templates/shop/yack/cms/_picto.html.twig
const stroke = 'fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"';

export const PICTOS: Record<string, { label: string; svg: string }> = {
	livraison: {
		label: 'Livraison',
		svg: `<g ${stroke}><path d="M5 17a2 2 0 1 0 4 0a2 2 0 1 0-4 0m10 0a2 2 0 1 0 4 0a2 2 0 1 0-4 0"/><path d="M5 17H3v-4M2 5h11v12m-4 0h6m4 0h2v-6h-8m0-5h5l3 5M3 9h4"/></g>`
	},
	garantie: {
		label: 'Garantie',
		svg: `<path ${stroke} d="M11.46 20.846A12 12 0 0 1 3.5 6A12 12 0 0 0 12 3a12 12 0 0 0 8.5 3a12 12 0 0 1-.09 7.06M15 19l2 2l4-4"/>`
	},
	sav: {
		label: 'SAV / outil',
		svg: `<path ${stroke} d="M7 10h3V7L6.5 3.5a6 6 0 0 1 8 8l6 6a2 2 0 0 1-3 3l-6-6a6 6 0 0 1-8-8z"/>`
	},
	stock: {
		label: 'Stock',
		svg: `<g ${stroke}><path d="m7 16.5l-5-3l5-3l5 3V19l-5 3z"/><path d="M2 13.5V19l5 3m0-5.455l5-3.03m5 2.985l-5-3l5-3l5 3V19l-5 3zM12 19l5 3m0-5.5l5-3m-10 0V8L7 5l5-3l5 3v5.5M7 5.03v5.455M12 8l5-3"/></g>`
	},
	paiement: {
		label: 'Paiement',
		svg: `<path ${stroke} d="M3 8a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3zm0 2h18M7 15h.01M11 15h2"/>`
	},
	france: {
		label: 'Proximité',
		svg: `<g ${stroke}><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/><path d="M17.657 16.657L13.414 20.9a2 2 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0"/></g>`
	},
	conseil: {
		label: 'Conseil',
		svg: `<g ${stroke}><path d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3h-5l-5 3v-3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3zM9.5 9h.01m4.99 0h.01"/><path d="M9.5 13a3.5 3.5 0 0 0 5 0"/></g>`
	},
	eco: {
		label: 'Écologie',
		svg: `<g ${stroke}><path d="M5 21c.5-4.5 2.5-8 7-10"/><path d="M9 18c6.218 0 10.5-3.288 11-12V4h-4.014c-9 0-11.986 4-12 9c0 1 0 3 2 5z"/></g>`
	},
	document: {
		label: 'Document',
		svg: `<g ${stroke}><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2M9 9h1m-1 4h6m-6 4h6"/></g>`
	},
	panier: {
		label: 'Panier',
		svg: `<g ${stroke}><path d="M4 19a2 2 0 1 0 4 0a2 2 0 1 0-4 0m11 0a2 2 0 1 0 4 0a2 2 0 1 0-4 0"/><path d="M17 17H6V3H4"/><path d="m6 5l14 1l-1 7H6"/></g>`
	},
	recherche: {
		label: 'Recherche',
		svg: `<path ${stroke} d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0-14 0m18 11l-6-6"/>`
	},
	livre: {
		label: 'Documentation',
		svg: `<path ${stroke} d="M3 19a9 9 0 0 1 9 0a9 9 0 0 1 9 0M3 6a9 9 0 0 1 9 0a9 9 0 0 1 9 0M3 6v13m9-13v13m9-13v13"/>`
	},
	compte: {
		label: 'Compte',
		svg: `<path ${stroke} d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0-8 0M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/>`
	}
};
