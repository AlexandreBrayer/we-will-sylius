// Yack block catalog for the builder. Mirrors App\Yack\Content\Domain\BlockType (Sylius
// rejects any other block, prop or choice value): editors only fill texts and pick among
// predefined visuals / themes — no colors, no CSS, no HTML. Free blocks (scope null) go
// everywhere; bound blocks only in a template of their scope, read its product / category.
import { PICTOS } from './pictos.js';
import type { StyleProperty } from './style.js';
import type { BlockProps, BlockType, TemplateType } from './types.js';
import { imageSettingNames } from './image.js';

/** Tab of the block panel (D10): Contenu (props), Style (style of the block), Avancé. */
export type TraitTab = 'contenu' | 'style' | 'avance';

export interface TraitDefinition {
	name: string;
	label: string;
	/** GrapesJS trait type: text, select, or a type of #lib/editor/traits/registerTraits.ts (textarea, yack-color…). */
	type: string;
	options?: { id: string; label: string }[];
	placeholder?: string;
	/** Tab of the block panel; default: contenu. */
	tab?: TraitTab;
	/** Style property edited by the trait (attribute `yb-<property>`, #lib/editor/panels/blockStyle.ts). */
	styleProperty?: StyleProperty;
	/** yack-choice: family of the charte giving the choices (BuilderMe.charte). */
	charte?: 'titleSizes' | 'spacings' | 'radii';
	/** yack-choice: a « Par défaut » choice first (stored as ''). */
	withDefault?: boolean;
	/** yack-choice: choice shown when the value is not set (picking it stores ''). */
	defaultId?: string;
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
	{ id: 'yack/cms/console.png', label: 'Console' },
	// Visuals of the commercial maquette (BlockType::IMAGES): photos, illustrations, brand logos.
	{ id: 'yack/cms/hero.jpg', label: 'Photo : salon résidentiel' },
	{ id: 'yack/cms/sol-pac-air-air.jpg', label: 'Photo : PAC air/air' },
	{ id: 'yack/cms/sol-pac-air-eau.jpg', label: 'Photo : PAC air/eau' },
	{ id: 'yack/cms/sol-ecs.jpg', label: 'Photo : eau chaude sanitaire' },
	{ id: 'yack/cms/sol-eau-glacee.jpg', label: 'Photo : eau glacée' },
	{ id: 'yack/cms/sol-thermostat.jpg', label: 'Photo : thermostat' },
	{ id: 'yack/cms/sect-petit-tertiaire.jpg', label: 'Photo : petit tertiaire' },
	{ id: 'yack/cms/sect-tertiaire.jpg', label: 'Photo : tertiaire' },
	{ id: 'yack/cms/sect-pandora.jpg', label: 'Photo : chalet' },
	{ id: 'yack/cms/illu-smart.png', label: 'Illustration : Smart Solutions' },
	{ id: 'yack/cms/illu-institute.png', label: 'Illustration : Technical Institute' },
	{ id: 'yack/cms/marque-mhi.png', label: 'Logo : MHI' },
	{ id: 'yack/cms/marque-rhoss.png', label: 'Logo : Rhoss' },
	{ id: 'yack/cms/marque-york.png', label: 'Logo : York' },
	{ id: 'yack/logo-yack.png', label: 'Logo : Yack' }
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

const PICTO_OPTIONS = Object.entries(PICTOS).map(([id, { label }]) => ({ id, label }));

/** Range tiles (BlockType::TILE_SLOTS): label, visual among the allowed ones, link. */
const tileTraits: TraitDefinition[] = [];
for (let i = 1; i <= 5; i++) {
	tileTraits.push(
		{ name: `label${i}`, label: `Tuile ${i}${i === 1 ? ' (la grande)' : ''} : libellé (vide = masquée)`, type: 'text' },
		{ name: `image${i}`, label: `Tuile ${i} : visuel`, type: 'select', options: [...IMAGES, { id: '', label: 'Aucun' }] },
		{ name: `url${i}`, label: `Tuile ${i} : lien`, type: 'text', placeholder: LINK_HINT }
	);
}

/** Support cards (BlockType::SUPPORT_SLOTS): heading, text, visual among the allowed ones, link. */
const supportTraits: TraitDefinition[] = [];
for (let i = 1; i <= 3; i++) {
	supportTraits.push(
		{ name: `heading${i}`, label: `Carte ${i} : titre (vide = masquée)`, type: 'text' },
		{ name: `text${i}`, label: `Carte ${i} : texte`, type: 'textarea' },
		{ name: `image${i}`, label: `Carte ${i} : visuel`, type: 'select', options: [...IMAGES, { id: '', label: 'Aucun' }] },
		{ name: `url${i}`, label: `Carte ${i} : lien (« Découvrir »)`, type: 'text', placeholder: LINK_HINT }
	);
}

const keyFigureTraits: TraitDefinition[] = [];
const quickAccessTraits: TraitDefinition[] = [];
for (let i = 1; i <= 4; i++) {
	keyFigureTraits.push(
		{ name: `value${i}`, label: `Chiffre ${i} (vide = masqué)`, type: 'text', placeholder: '48 h, 30 ans, 1 200…' },
		{ name: `label${i}`, label: `Chiffre ${i} : légende`, type: 'text' }
	);
	quickAccessTraits.push(
		{ name: `icon${i}`, label: `Accès ${i} : picto`, type: 'select', options: PICTO_OPTIONS },
		{ name: `label${i}`, label: `Accès ${i} : libellé (vide = masqué)`, type: 'text' },
		{ name: `text${i}`, label: `Accès ${i} : texte (facultatif)`, type: 'text' },
		{ name: `url${i}`, label: `Accès ${i} : lien`, type: 'text', placeholder: LINK_HINT },
		{
			name: `highlight${i}`,
			label: `Accès ${i} : mise en avant`,
			type: 'select',
			options: [
				{ id: 'non', label: 'Non' },
				{ id: 'oui', label: 'Oui (fond jaune)' }
			]
		}
	);
}

/** Every free block can target a customer profile (BlockType::AUDIENCES, first value = default). */
const audience: TraitDefinition = {
	name: 'audience',
	label: 'Profil ciblé',
	type: 'select',
	tab: 'avance',
	options: [
		{ id: 'tous', label: 'Tout le monde' },
		{ id: 'visiteur', label: 'Visiteurs non connectés' },
		{ id: 'pro', label: 'Comptes PRO' },
		{ id: 'ecom', label: 'Comptes ECOM' },
		{ id: 'connectes', label: 'Clients connectés (PRO et ECOM)' }
	]
};

const FREE_BLOCKS: Omit<BlockDefinition, 'scope'>[] = [
	{
		type: 'hero',
		label: 'Bandeau principal',
		description: 'Titre, accroche, visuel ou carte selon le profil, deux boutons',
		icon: svg('<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M6 9h7M6 12h5"/><rect x="15" y="8" width="3" height="5" rx="1"/>'),
		traits: [
			{ name: 'kicker', label: 'Surtitre (facultatif)', type: 'text' },
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
					{ id: 'clair', label: 'Clair' },
					{ id: 'photo', label: 'Photo (le visuel en fond, dégradé de la maquette)' }
				]
			},
			...cta,
			{ name: 'cta2Label', label: 'Second bouton : libellé (vide = pas de bouton)', type: 'text' },
			{ name: 'cta2Url', label: 'Second bouton : lien', type: 'text', placeholder: LINK_HINT },
			{
				name: 'card',
				label: 'Carte à droite',
				type: 'select',
				options: [
					{ id: 'non', label: 'Aucune (visuel)' },
					{ id: 'profil', label: 'Selon le profil (remplace le visuel)' }
				]
			}
		],
		defaults: {
			kicker: '',
			title: 'Titre du bandeau',
			subtitle: 'Une accroche courte qui donne envie de découvrir la suite.',
			image: 'yack/cms/pac-monobloc.png',
			theme: 'magenta',
			ctaLabel: 'Découvrir',
			ctaUrl: '/produits',
			cta2Label: '',
			cta2Url: '',
			card: 'non'
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
				placeholder: 'SRK25ZS-WFT, SRC25ZS-W2'
			}
		],
		defaults: {
			title: 'Nos pompes à chaleur',
			subtitle: '',
			productCodes: 'SRK20ZS-WFT, SRK25ZS-WFT, SRK25ZS-WF'
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
				placeholder: 'pac_air_eau, pac_air_air_residentiel_ui_mural'
			}
		],
		defaults: {
			title: 'Nos gammes',
			taxonCodes: 'pac_air_air, pac_air_eau, ecs, eau_glacee'
		}
	},
	{
		type: 'cta',
		label: "Bandeau d'appel à l'action",
		description: 'Surtitre, message court et bouton (actualité, réglementation…)',
		icon: svg('<rect x="2" y="6" width="20" height="12" rx="3"/><path d="M6 12h6"/><rect x="14" y="10" width="5" height="4" rx="1"/>'),
		traits: [
			{ name: 'kicker', label: 'Surtitre (facultatif)', type: 'text', placeholder: 'Réglementation F-Gas' },
			{ name: 'title', label: 'Titre', type: 'text' },
			{ name: 'text', label: 'Texte', type: 'textarea' },
			{
				name: 'theme',
				label: 'Fond',
				type: 'select',
				options: [
					{ id: 'magenta', label: 'Magenta Yack' },
					{ id: 'sombre', label: 'Sombre' },
					{ id: 'turquoise', label: 'Turquoise' },
					{ id: 'prune', label: 'Prune' },
					{ id: 'jaune', label: 'Jaune' }
				]
			},
			...cta
		],
		defaults: {
			kicker: '',
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
				'Votre première question ? | Sa réponse, en une ou deux phrases.\nUne autre question ? | Sa réponse.'
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
			title1: 'Premier argument',
			text1: 'Une phrase pour le préciser',
			icon2: 'garantie',
			title2: 'Deuxième argument',
			text2: 'Une phrase pour le préciser',
			icon3: 'conseil',
			title3: 'Troisième argument',
			text3: 'Une phrase pour le préciser',
			icon4: 'paiement',
			title4: '',
			text4: ''
		}
	},
	{
		type: 'chiffres-cles',
		label: 'Chiffres clés',
		description: '2 à 4 chiffres avec légende',
		icon: svg('<path d="M4 19h16M7 15V9M12 15V5M17 15v-4"/>'),
		traits: [
			{ name: 'title', label: 'Titre (facultatif)', type: 'text' },
			{
				name: 'theme',
				label: 'Fond',
				type: 'select',
				options: [
					{ id: 'clair', label: 'Clair' },
					{ id: 'magenta', label: 'Magenta Yack' },
					{ id: 'sombre', label: 'Sombre' }
				]
			},
			...keyFigureTraits
		],
		defaults: {
			title: '',
			theme: 'clair',
			value1: '12',
			label1: 'Légende du premier chiffre',
			value2: '100 %',
			label2: 'Légende du deuxième chiffre',
			value3: '+ 300',
			label3: 'Légende du troisième chiffre',
			value4: '',
			label4: ''
		}
	},
	{
		type: 'acces-rapides',
		label: 'Accès rapides',
		description: 'Tuiles picto, libellé, texte et lien vers les parcours clés',
		icon: svg('<rect x="3" y="4" width="8" height="7" rx="2"/><rect x="13" y="4" width="8" height="7" rx="2"/><rect x="3" y="13" width="8" height="7" rx="2"/><rect x="13" y="13" width="8" height="7" rx="2"/>'),
		traits: [{ name: 'title', label: 'Titre (facultatif)', type: 'text' }, ...quickAccessTraits],
		defaults: {
			title: '',
			icon1: 'recherche',
			label1: 'Pièces détachées',
			text1: 'Le n° de série suffit : modèle, vue éclatée, pièces d’origine.',
			url1: '/sav',
			highlight1: 'non',
			icon2: 'france',
			label2: 'Trouver un installateur',
			text2: 'Un réseau de pros près de chez vos clients.',
			url2: '/trouver-un-installateur',
			highlight2: 'oui',
			icon3: 'panier',
			label3: 'Commander en ligne',
			text3: 'Prix et panier pour les comptes ECOM.',
			url3: '/produits',
			highlight3: 'non',
			icon4: 'document',
			label4: 'Suivre mes commandes',
			text4: '',
			url4: '/compte/commandes',
			highlight4: 'non'
		}
	},
	{
		type: 'tuiles',
		label: 'Tuiles de gammes',
		description: 'Une grande tuile et quatre, ou une grille, avec visuel',
		icon: svg('<rect x="3" y="4" width="9" height="16" rx="1"/><rect x="14" y="4" width="3" height="7" rx="1"/><rect x="18" y="4" width="3" height="7" rx="1"/><rect x="14" y="13" width="3" height="7" rx="1"/><rect x="18" y="13" width="3" height="7" rx="1"/>'),
		traits: [
			{ name: 'title', label: 'Titre (facultatif)', type: 'text' },
			{
				name: 'layout',
				label: 'Disposition',
				type: 'select',
				options: [
					{ id: 'grande-quatre', label: 'Une grande tuile et quatre' },
					{ id: 'grille', label: 'Grille (4 par ligne, 2 en mobile)' }
				]
			},
			...tileTraits
		],
		defaults: {
			title: 'Nos solutions',
			layout: 'grande-quatre',
			label1: 'Pompes à chaleur air/eau',
			image1: 'yack/cms/pac-monobloc.png',
			url1: '/produits/pompe-a-chaleur-air-eau',
			label2: 'Pompes à chaleur air/air',
			image2: 'yack/cms/pac-split.png',
			url2: '/produits/pompe-a-chaleur-air-air',
			label3: 'Muraux résidentiels',
			image3: 'yack/cms/split-mural.png',
			url3: '/produits/pompe-a-chaleur-air-air/residentiel/unite-interieure/mural',
			label4: 'Gainables petit tertiaire',
			image4: 'yack/cms/gainable.png',
			url4: '/produits/pompe-a-chaleur-air-air/petit-tertiaire/unite-interieure/gainable',
			label5: 'Eau chaude sanitaire',
			image5: 'yack/cms/ballon-thermo.png',
			url5: '/produits/eau-chaude-sanitaire'
		}
	},
	{
		type: 'accompagnement',
		label: "Cartes d'accompagnement",
		description: 'Trois cartes titre, texte, visuel et « Découvrir » (groupe, services, formation)',
		icon: svg('<rect x="2" y="6" width="6" height="12" rx="1"/><rect x="9" y="6" width="6" height="12" rx="1"/><rect x="16" y="6" width="6" height="12" rx="1"/><path d="M4 9h2M11 9h2M18 9h2"/>'),
		traits: [{ name: 'title', label: 'Titre (facultatif)', type: 'text' }, ...supportTraits],
		defaults: {
			title: 'Pour vous accompagner',
			heading1: 'Qui sommes-nous',
			text1: 'Un acteur du confort thermique au service des installateurs.',
			image1: 'yack/logo-yack.png',
			url1: 'page:le-groupe',
			heading2: 'Yack Smart Solutions',
			text2: 'Nous vous accompagnons dans votre transition technologique et environnementale.',
			image2: 'yack/cms/illu-smart.png',
			url2: 'page:yack-smart-solutions',
			heading3: 'Yack Technical Institute',
			text3: 'Notre centre de formation pour les installateurs.',
			image3: 'yack/cms/illu-institute.png',
			url3: 'page:yack-technical-institute'
		}
	},
	{
		type: 'documents',
		label: 'Documents à télécharger',
		description: 'Notices, fiches techniques, brochures',
		icon: svg('<path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2"/><path d="M12 11v6m-3-3l3 3l3-3"/>'),
		traits: [
			{ name: 'title', label: 'Titre', type: 'text' },
			{ name: 'text', label: 'Introduction (facultatif)', type: 'textarea' },
			{
				name: 'items',
				label: 'Documents : un par ligne, « Libellé | lien »',
				type: 'textarea',
				placeholder: 'Notice Evolys 8 kW | /media/docs/notice-evolys.pdf'
			}
		],
		defaults: {
			title: 'Documentations',
			text: '',
			items: 'Catalogue Clik&Yack | /produits\nNos services | page:nos-services'
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
				label: 'Produits par page',
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

export const REGISTRY: BlockDefinition[] = [
	...FREE_BLOCKS.map((entry) => ({
		...entry,
		traits: [...entry.traits, audience],
		defaults: { ...entry.defaults, audience: 'tous' },
		scope: null
	})),
	...BOUND_BLOCKS
];

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

// ── Lists (D9): props stored in lines « a | b », edited element by element (yack-list) ──

export interface TraitDefinition {
	/** yack-list: columns of a line, in order (#lib/editor/traits/list.ts). */
	columns?: import('../editor/traits/list.js').ListColumn[];
	/** yack-list: name of one element (« Question »), for the cards and their buttons. */
	itemLabel?: string;
	/** yack-list: label of the add button. */
	addLabel?: string;
}

/**
 * Props of the blocks that are lists, one line per element, columns separated by « | » (read by
 * #lib/blocks/parse.ts): block type, prop, then its field. Applied to REGISTRY when the module loads.
 */
export const LIST_TRAITS: Partial<Record<BlockType, Record<string, Pick<TraitDefinition, 'label' | 'columns' | 'itemLabel' | 'addLabel'>>>> = {
	faq: {
		items: {
			label: 'Questions',
			itemLabel: 'Question',
			addLabel: 'Ajouter une question',
			columns: [
				{ label: 'Question', required: true, placeholder: 'Votre question ?' },
				{ label: 'Réponse', multiline: true, placeholder: 'Sa réponse, en une ou deux phrases.' }
			]
		}
	},
	documents: {
		items: {
			label: 'Documents',
			itemLabel: 'Document',
			addLabel: 'Ajouter un document',
			columns: [
				{ label: 'Libellé', required: true, placeholder: 'Notice Evolys 8 kW' },
				{ label: 'Lien', required: true, link: true, placeholder: LINK_HINT }
			]
		}
	}
};

for (const entry of REGISTRY) {
	entry.traits = entry.traits.map((trait) => {
		const list = LIST_TRAITS[entry.type]?.[trait.name];
		return list ? { ...trait, ...list, type: 'yack-list' } : trait;
	});
}

// ── Style of a block (D1, D9, D10): every block, free or bound, has the same Style and Avancé fields ──

/**
 * Fields of the block style (Content\Domain\BlockStyle), after the block's own traits: tab Style
 * (colours, title size, spacings, width, alignment, radius) and Avancé (hiding, anchor). The value
 * lives in the attribute `yb-<property>` of the component (#lib/editor/panels/blockStyle.ts).
 */
export const STYLE_TRAITS: TraitDefinition[] = [
	{ name: 'yb-fond', label: 'Fond', type: 'yack-color', tab: 'style', styleProperty: 'fond' },
	{ name: 'yb-texte', label: 'Texte', type: 'yack-color', tab: 'style', styleProperty: 'texte' },
	{ name: 'yb-accent', label: 'Accent', type: 'yack-color', tab: 'style', styleProperty: 'accent' },
	{ name: 'yb-titre', label: 'Taille du titre', type: 'yack-choice', tab: 'style', styleProperty: 'titre', charte: 'titleSizes', withDefault: true },
	{ name: 'yb-hautEspace', label: 'Espace au-dessus', type: 'yack-choice', tab: 'style', styleProperty: 'hautEspace', charte: 'spacings', withDefault: true },
	{ name: 'yb-basEspace', label: 'Espace en dessous', type: 'yack-choice', tab: 'style', styleProperty: 'basEspace', charte: 'spacings', withDefault: true },
	{
		name: 'yb-largeur',
		label: 'Largeur',
		type: 'yack-choice',
		tab: 'style',
		styleProperty: 'largeur',
		defaultId: 'contenu',
		options: [
			{ id: 'contenu', label: 'Contenu' },
			{ id: 'large', label: 'Large' },
			{ id: 'pleine', label: 'Pleine' }
		]
	},
	{
		name: 'yb-alignement',
		label: 'Alignement',
		type: 'yack-choice',
		tab: 'style',
		styleProperty: 'alignement',
		withDefault: true,
		options: [
			{ id: 'gauche', label: 'Gauche' },
			{ id: 'centre', label: 'Centre' },
			{ id: 'droite', label: 'Droite' }
		]
	},
	{ name: 'yb-arrondi', label: 'Arrondi', type: 'yack-choice', tab: 'style', styleProperty: 'arrondi', charte: 'radii', withDefault: true },
	...(['masquerMobile', 'masquerOrdinateur'] as const).map(
		(property): TraitDefinition => ({
			name: `yb-${property}`,
			label: property === 'masquerMobile' ? 'Masquer sur mobile' : 'Masquer sur ordinateur',
			type: 'yack-choice',
			tab: 'avance',
			styleProperty: property,
			defaultId: 'non',
			options: [
				{ id: 'non', label: 'Non' },
				{ id: 'oui', label: 'Oui' }
			]
		})
	),
	{ name: 'yb-ancre', label: 'Ancre', type: 'yack-anchor', tab: 'avance', styleProperty: 'ancre', placeholder: 'nos-services' }
];

// ── Link fields (D9): a search of the CMS pages, categories, products and shortcuts, or a typed address ──

/** Props holding a link (resolveLink): `ctaUrl`, `cta2Url`, and `url<n>` of the tiles, cards and quick accesses. */
export const LINK_PROP = /^(cta2?Url|url\d+)$/;

// Every link prop is edited by the yack-link field (#lib/editor/traits/YackLink.svelte); the stored
// value keeps its format (page:<slug>, /sav, https://…). Set here, after the block definitions.
for (const entry of REGISTRY) {
	for (const trait of entry.traits) {
		if (!LINK_PROP.test(trait.name)) continue;
		trait.type = 'yack-link';
		delete trait.placeholder;
	}
}

// ── Images (D7, D9): every image prop is edited with the media library, with its settings ──

/** Image props of each block (BlockType::imageProps()). */
export const IMAGE_PROPS: Partial<Record<BlockType, string[]>> = {
	hero: ['image'],
	'texte-image': ['image'],
	tuiles: ['image1', 'image2', 'image3', 'image4', 'image5'],
	accompagnement: ['image1', 'image2', 'image3']
};

// The field of an image prop is `yack-image` (#lib/editor/traits/YackImage.svelte): thumbnail, « Choisir
// une image » (media library), « Retirer » when the image is optional (`options` of the trait: the historical
// visuals with their labels, and '' when the block accepts no image), and the settings of the image
// (#lib/blocks/image.ts: alternative text, framing, focal point), whose props get their default '' (not set).
for (const entry of REGISTRY) {
	for (const prop of IMAGE_PROPS[entry.type] ?? []) {
		const trait = entry.traits.find((candidate) => candidate.name === prop);
		if (trait) trait.type = 'yack-image';
		const names = imageSettingNames(prop);
		entry.defaults = { ...entry.defaults, [names.alt]: '', [names.fit]: '', [names.focus]: '' };
	}
}
