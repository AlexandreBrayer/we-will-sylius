// Yack block catalog for the builder. Mirrors App\Yack\Content\Domain\BlockType (Sylius
// rejects any other block, prop or choice value): editors only fill texts and pick among
// predefined visuals / themes — no colors, no CSS, no HTML. Free blocks (scope null) go
// everywhere; bound blocks only in a template of their scope, read its product / category.
import { PICTOS } from './pictos.js';
import type { BlockProps, BlockType, TemplateType } from './types.js';

export interface TraitDefinition {
	name: string;
	label: string;
	type: 'text' | 'textarea' | 'select';
	options?: { id: string; label: string }[];
	placeholder?: string;
}

export interface BlockDefinition {
	type: BlockType;
	label: string;
	description: string;
	icon: string;
	traits: TraitDefinition[];
	defaults: BlockProps;
	/** Template type of a bound block; null = free block (pages and templates). */
	scope: TemplateType | null;
	/** Exactly once in every template of its scope (cannot be removed / duplicated). */
	mandatory?: boolean;
}

const IMAGES = [
	{ id: 'yack/cms/pac-monobloc.png', label: 'PAC monobloc' },
	{ id: 'yack/cms/pac-split.png', label: 'PAC split' },
	{ id: 'yack/cms/split-mural.png', label: 'Climatiseur mural' },
	{ id: 'yack/cms/ballon-thermo.png', label: 'Ballon thermodynamique' },
	{ id: 'yack/cms/gainable.png', label: 'Gainable' },
	{ id: 'yack/cms/console.png', label: 'Console' }
];

const LINK_HINT = '/sav, /produits, page:nos-services ou https://…';

const cta: TraitDefinition[] = [
	{ name: 'ctaLabel', label: 'Bouton : libellé (vide = pas de bouton)', type: 'text' },
	{ name: 'ctaUrl', label: 'Bouton : lien', type: 'text', placeholder: LINK_HINT }
];

const svg = (body: string) =>
	`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">${body}</svg>`;

const reassuranceTraits: TraitDefinition[] = [];
for (let i = 1; i <= 4; i++) {
	reassuranceTraits.push(
		{
			name: `icon${i}`,
			label: `Argument ${i} : picto`,
			type: 'select',
			options: Object.entries(PICTOS).map(([id, { label }]) => ({ id, label }))
		},
		{ name: `title${i}`, label: `Argument ${i} : titre (vide = masqué)`, type: 'text' },
		{ name: `text${i}`, label: `Argument ${i} : texte`, type: 'text' }
	);
}

const FREE_BLOCKS: Omit<BlockDefinition, 'scope'>[] = [
	{
		type: 'hero',
		label: 'Bandeau principal',
		description: 'Titre, accroche, visuel et bouton',
		icon: svg('<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M6 9h7M6 12h5"/><rect x="15" y="8" width="3" height="5" rx="1"/>'),
		traits: [
			{ name: 'title', label: 'Titre', type: 'text' },
			{ name: 'subtitle', label: 'Accroche', type: 'textarea' },
			{ name: 'image', label: 'Visuel', type: 'select', options: [...IMAGES, { id: '', label: 'Aucun' }] },
			{
				name: 'theme',
				label: 'Fond',
				type: 'select',
				options: [
					{ id: 'magenta', label: 'Magenta Yack' },
					{ id: 'sombre', label: 'Sombre' },
					{ id: 'clair', label: 'Clair' }
				]
			},
			...cta
		],
		defaults: {
			title: 'Titre du bandeau',
			subtitle: 'Une accroche courte qui donne envie de découvrir la suite.',
			image: 'yack/cms/pac-monobloc.png',
			theme: 'magenta',
			ctaLabel: 'Découvrir',
			ctaUrl: '/produits'
		}
	},
	{
		type: 'texte-image',
		label: 'Texte + image',
		description: 'Paragraphe avec visuel à gauche ou à droite',
		icon: svg('<rect x="3" y="5" width="8" height="14" rx="1"/><path d="M14 8h7M14 12h7M14 16h4"/>'),
		traits: [
			{ name: 'title', label: 'Titre', type: 'text' },
			{ name: 'text', label: 'Texte', type: 'textarea' },
			{ name: 'image', label: 'Visuel', type: 'select', options: IMAGES },
			{
				name: 'imagePosition',
				label: 'Position du visuel',
				type: 'select',
				options: [
					{ id: 'gauche', label: 'À gauche' },
					{ id: 'droite', label: 'À droite' }
				]
			},
			...cta
		],
		defaults: {
			title: 'Un titre de section',
			text: 'Présentez ici un service, une gamme ou un argument. Le texte reste lisible sur tous les écrans.',
			image: 'yack/cms/pac-split.png',
			imagePosition: 'gauche',
			ctaLabel: '',
			ctaUrl: ''
		}
	},
	{
		type: 'produits',
		label: 'Produits mis en avant',
		description: 'Fiches produits Sylius (codes)',
		icon: svg('<rect x="3" y="4" width="5" height="8" rx="1"/><rect x="10" y="4" width="5" height="8" rx="1"/><rect x="17" y="4" width="4" height="8" rx="1"/><path d="M3 16h5M10 16h5M17 16h4"/>'),
		traits: [
			{ name: 'title', label: 'Titre', type: 'text' },
			{ name: 'subtitle', label: "Texte d'introduction", type: 'text' },
			{
				name: 'productCodes',
				label: 'Codes produits (séparés par des virgules)',
				type: 'textarea',
				placeholder: 'YK-PAE-M06, YK-PAE-M08'
			}
		],
		defaults: {
			title: 'Nos pompes à chaleur',
			subtitle: '',
			productCodes: 'YK-PAE-M06, YK-PAE-M08, YK-PAE-S11'
		}
	},
	{
		type: 'categories',
		label: 'Catégories phares',
		description: 'Liens vers des rubriques du catalogue',
		icon: svg('<rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/>'),
		traits: [
			{ name: 'title', label: 'Titre', type: 'text' },
			{
				name: 'taxonCodes',
				label: 'Codes catégories (séparés par des virgules)',
				type: 'textarea',
				placeholder: 'pac_air_eau, clim_murale'
			}
		],
		defaults: {
			title: 'Nos gammes',
			taxonCodes: 'pac_air_eau, pac_air_air, clim_murale, ballons_thermodynamiques'
		}
	},
	{
		type: 'cta',
		label: "Bandeau d'appel à l'action",
		description: 'Message court et bouton',
		icon: svg('<rect x="2" y="6" width="20" height="12" rx="3"/><path d="M6 12h6"/><rect x="14" y="10" width="5" height="4" rx="1"/>'),
		traits: [
			{ name: 'title', label: 'Titre', type: 'text' },
			{ name: 'text', label: 'Texte', type: 'textarea' },
			{
				name: 'theme',
				label: 'Fond',
				type: 'select',
				options: [
					{ id: 'magenta', label: 'Magenta Yack' },
					{ id: 'sombre', label: 'Sombre' }
				]
			},
			...cta
		],
		defaults: {
			title: 'Vous êtes installateur ?',
			text: 'Créez votre compte PRO et accédez aux prix professionnels.',
			theme: 'magenta',
			ctaLabel: 'Créer mon compte',
			ctaUrl: '/inscription'
		}
	},
	{
		type: 'faq',
		label: 'FAQ',
		description: 'Questions / réponses dépliables',
		icon: svg('<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5v.7M12 17h.01"/>'),
		traits: [
			{ name: 'title', label: 'Titre', type: 'text' },
			{
				name: 'items',
				label: 'Questions : une par ligne, « Question | Réponse »',
				type: 'textarea',
				placeholder: 'Question ? | Réponse'
			}
		],
		defaults: {
			title: 'Questions fréquentes',
			items:
				'Quel délai de livraison ? | 48 h en France métropolitaine, sur chantier ou en agence.\nLes produits sont-ils garantis ? | Oui, garantie constructeur Yack (jusqu’à 5 ans sur les compresseurs).'
		}
	},
	{
		type: 'reassurance',
		label: 'Réassurance',
		description: '3 ou 4 arguments avec picto',
		icon: svg('<path d="M12 3l7 3v5c0 5-3.5 8.5-7 10c-3.5-1.5-7-5-7-10V6z"/><path d="M9 12l2 2l4-4"/>'),
		traits: [{ name: 'title', label: 'Titre (facultatif)', type: 'text' }, ...reassuranceTraits],
		defaults: {
			title: '',
			icon1: 'livraison',
			title1: 'Livraison 48 h',
			text1: 'Sur chantier ou en agence',
			icon2: 'garantie',
			title2: 'Garantie constructeur',
			text2: 'Pièces et main-d’œuvre',
			icon3: 'conseil',
			title3: 'Conseil technique',
			text3: 'Une hotline dédiée',
			icon4: 'paiement',
			title4: '',
			text4: ''
		}
	}
];

const titleTrait = (fallback: string): TraitDefinition => ({
	name: 'title',
	label: 'Titre (vide = « ' + fallback + ' »)',
	type: 'text',
	placeholder: fallback
});

const BOUND_BLOCKS: BlockDefinition[] = [
	{
		type: 'produit-galerie',
		scope: 'product',
		label: 'Galerie produit',
		description: 'Visuel principal et vignettes du produit',
		icon: svg('<rect x="3" y="3" width="18" height="13" rx="2"/><path d="M3 13l5-4 4 3 3-2 6 4"/><rect x="3" y="18" width="4" height="3" rx=".5"/><rect x="9" y="18" width="4" height="3" rx=".5"/>'),
		traits: [],
		defaults: {}
	},
	{
		type: 'produit-infos',
		scope: 'product',
		label: 'Informations produit',
		description: 'Nom, marque, gamme, code, description courte',
		icon: svg('<path d="M4 5h16M4 10h10M4 15h12M4 20h7"/>'),
		traits: [],
		defaults: {}
	},
	{
		type: 'produit-achat',
		scope: 'product',
		mandatory: true,
		label: 'Achat (prix, stock, panier)',
		description: 'Prix et panier (ECOM), messages visiteur / PRO',
		icon: svg('<path d="M3 4h2l2.4 11h11l2-8H6.2"/><circle cx="9" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/>'),
		traits: [],
		defaults: {}
	},
	{
		type: 'produit-caracteristiques',
		scope: 'product',
		label: 'Caractéristiques techniques',
		description: 'Tableau des attributs (puissance, SCOP…)',
		icon: svg('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M3 14h18M10 4v16"/>'),
		traits: [titleTrait('Caractéristiques techniques')],
		defaults: { title: '' }
	},
	{
		type: 'produit-description',
		scope: 'product',
		label: 'Description',
		description: 'Description détaillée du produit',
		icon: svg('<path d="M4 5h16M4 9h16M4 13h16M4 17h10"/>'),
		traits: [titleTrait('Description')],
		defaults: { title: '' }
	},
	{
		type: 'produit-documents',
		scope: 'product',
		label: 'Documents',
		description: 'Notices et fiches techniques à télécharger',
		icon: svg('<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/>'),
		traits: [titleTrait('Documents')],
		defaults: { title: '' }
	},
	{
		type: 'produit-sav',
		scope: 'product',
		label: 'Lien SAV',
		description: 'Encadré vers les pièces détachées (n° de série)',
		icon: svg('<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>'),
		traits: [
			titleTrait('Une pièce détachée pour cet appareil ?'),
			{
				name: 'text',
				label: 'Texte (vide = texte par défaut)',
				type: 'textarea',
				placeholder: "Saisissez le numéro de série de l'appareil pour retrouver ses vues éclatées et ses pièces d'origine."
			}
		],
		defaults: { title: '', text: '' }
	},
	{
		type: 'produits-associes',
		scope: 'product',
		label: 'Produits associés',
		description: 'Produits liés ou de la même catégorie',
		icon: svg('<rect x="3" y="5" width="7" height="9" rx="1"/><rect x="14" y="5" width="7" height="9" rx="1"/><path d="M10 9.5h4M3 18h7M14 18h7"/>'),
		traits: [
			titleTrait('Produits associés'),
			{
				name: 'limit',
				label: 'Nombre de produits',
				type: 'select',
				options: [
					{ id: '4', label: '4' },
					{ id: '8', label: '8' }
				]
			}
		],
		defaults: { title: '', limit: '4' }
	},
	{
		type: 'categorie-entete',
		scope: 'taxon',
		label: 'En-tête de catégorie',
		description: 'Nom et description de la catégorie',
		icon: svg('<rect x="3" y="4" width="18" height="6" rx="1"/><path d="M3 14h18M3 18h12"/>'),
		traits: [
			{
				name: 'description',
				label: 'Description de la catégorie',
				type: 'select',
				options: [
					{ id: 'oui', label: 'Affichée' },
					{ id: 'non', label: 'Masquée' }
				]
			}
		],
		defaults: { description: 'oui' }
	},
	{
		type: 'categorie-produits',
		scope: 'taxon',
		mandatory: true,
		label: 'Liste des produits',
		description: 'Filtres, tri et pagination',
		icon: svg('<path d="M3 5h4M3 10h4M3 15h4"/><rect x="10" y="4" width="5" height="6" rx="1"/><rect x="17" y="4" width="4" height="6" rx="1"/><rect x="10" y="13" width="5" height="6" rx="1"/><rect x="17" y="13" width="4" height="6" rx="1"/>'),
		traits: [
			{
				name: 'perPage',
				label: 'Produits par page (initial)',
				type: 'select',
				options: ['9', '18', '27'].map((id) => ({ id, label: id }))
			},
			{
				name: 'sort',
				label: 'Tri initial',
				type: 'select',
				options: [
					{ id: 'position', label: 'Par position' },
					{ id: 'nom-asc', label: 'De A à Z' },
					{ id: 'nom-desc', label: 'De Z à A' },
					{ id: 'recent', label: 'Plus récent en premier' },
					{ id: 'ancien', label: 'Plus ancien en premier' }
				]
			}
		],
		defaults: { perPage: '9', sort: 'position' }
	},
	{
		type: 'categorie-sous-categories',
		scope: 'taxon',
		label: 'Sous-catégories',
		description: 'Cartes des sous-catégories',
		icon: svg('<rect x="3" y="3" width="18" height="5" rx="1"/><rect x="3" y="11" width="5" height="10" rx="1"/><rect x="10" y="11" width="5" height="10" rx="1"/><rect x="17" y="11" width="4" height="10" rx="1"/>'),
		traits: [titleTrait('Sous-catégories')],
		defaults: { title: '' }
	}
];

export const REGISTRY: BlockDefinition[] = [...FREE_BLOCKS.map((entry) => ({ ...entry, scope: null })), ...BOUND_BLOCKS];

export function blockDefinition(type: string): BlockDefinition | undefined {
	return REGISTRY.find((entry) => entry.type === type);
}

/** Blocks allowed in a page (null: free blocks only) or in a template of `type` (+ its bound blocks). */
export function allowedBlocks(type: TemplateType | null): BlockDefinition[] {
	return REGISTRY.filter((entry) => entry.scope === null || entry.scope === type);
}

/** Keep only the props Sylius accepts for this block type (GrapesJS may add id/class attributes). */
export function pickProps(type: string, attributes: Record<string, unknown>): BlockProps {
	const definition = blockDefinition(type);
	const props: BlockProps = {};
	for (const name of Object.keys(definition?.defaults ?? {})) {
		const value = attributes[name];
		props[name] = typeof value === 'string' ? value : (definition?.defaults[name] ?? '');
	}
	return props;
}
