// Onglet Style d'un bloc (E4, avenant B8-3) : seuls les réglages qui changent le bloc (registry.ts, styleTraits()),
// pictogrammes des choix (yack-icons), `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { after, before, describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createServer, type ViteDevServer } from 'vite';
import { SPACING_SIDES } from '../src/lib/editor/traits/spacing.ts';

const root = new URL('../../', import.meta.url);
const read = (path: string): string => readFileSync(new URL(path, root), 'utf8');
const BLOCKS = 'front/src/lib/blocks/';

/** Source d'un composant de bloc et des composants qu'il importe à côté de lui (un niveau). */
function componentSource(file: string): string {
	const text = read(`${BLOCKS}${file}`);
	const dir = file.includes('/') ? file.slice(0, file.lastIndexOf('/') + 1) : '';
	const children = [...text.matchAll(/import \w+ from '\.\/([\w/]+\.svelte)'/g)].map((m) => read(`${BLOCKS}${dir}${m[1]}`));
	return [text, ...children].join('\n');
}

/** Type de bloc => source de son composant (components.ts). */
function componentSources(): Map<string, string> {
	const text = read(`${BLOCKS}components.ts`);
	const files = new Map([...text.matchAll(/^import (\w+) from '\.\/([\w/]+\.svelte)';$/gm)].map((m) => [m[1], m[2]]));
	const sources = new Map<string, string>();
	for (const [, type, name] of text.matchAll(/^\t'?([a-z-]+)'?: (?:propless\()?(\w+)\)?,?$/gm)) {
		const file = files.get(name);
		if (file) sources.set(type, componentSource(file));
	}
	return sources;
}

/** Classes que `.yack-cms-block--accent` colore (_cms-blocks.scss). */
function accentClasses(): string[] {
	const scss = read('assets/shop/styles/_cms-blocks.scss');
	const start = scss.indexOf('.yack-cms-block--accent {');
	const block = scss.slice(start, scss.indexOf('\n}\n', start));
	return [...new Set([...block.matchAll(/\.(yack-cms-[a-z_-]+)/g)].map((m) => m[1]).filter((name) => name !== 'yack-cms-block--accent'))];
}

/** Un titre que `.yack-cms-block--titre` agrandit : h1, h2 (ou leur classe), ou la balise choisie (`<svelte:element>`). */
const HEADING = /<h[12][\s>]|<svelte:element\b|class="[^"]*\bh[12]\b/;

describe('onglet Style : les réglages qui changent le bloc', () => {
	let server: ViteDevServer;
	let registry: typeof import('../src/lib/blocks/registry.ts');

	before(async () => {
		server = await createServer({
			root: fileURLToPath(new URL('../', import.meta.url)),
			logLevel: 'error',
			server: { middlewareMode: true, hmr: false, watch: null },
			appType: 'custom'
		});
		registry = (await server.ssrLoadModule('/src/lib/blocks/registry.ts')) as typeof registry;
	});
	after(() => server.close());

	/** Champs de l'onglet Style d'un bloc : ses champs propres (`tab: 'style'`), puis ceux du style, chaque côté de l'espacement compris. */
	const styleTab = (type: string): string[] => [
		...(registry.blockDefinition(type)?.traits ?? []).filter((trait) => trait.tab === 'style').map((trait) => trait.name),
		...registry
			.styleTraits(type)
			.filter((trait) => trait.tab === 'style')
			.flatMap((trait): string[] => [...(trait.styleProperties ?? [trait.styleProperty ?? trait.name])])
	];

	it('Texte + image : couleurs sans accent (rien à accentuer), titre, mise en page de la maquette dont la position du visuel', () => {
		assert.deepEqual(styleTab('texte-image'), [
			'imagePosition',
			'fond',
			'texte',
			'titre',
			'hautEspace',
			'droiteEspace',
			'basEspace',
			'gaucheEspace',
			'largeur',
			'alignement',
			'arrondi',
			'balise',
			'alignementVertical'
		]);
	});

	it('blocs de base : Titre, Texte, Bouton, Espace', () => {
		const layout = ['hautEspace', 'droiteEspace', 'basEspace', 'gaucheEspace', 'largeur'];
		assert.deepEqual(styleTab('titre'), ['fond', 'texte', 'titre', ...layout, 'alignement', 'arrondi', 'balise']);
		assert.deepEqual(styleTab('texte'), ['fond', 'texte', ...layout, 'alignement', 'arrondi']);
		assert.deepEqual(styleTab('bouton'), ['fond', 'accent', ...layout, 'alignement', 'arrondi']);
		assert.deepEqual(styleTab('espace'), ['fond', 'accent', ...layout, 'arrondi']);
	});

	it('« Taille du titre » sur les seuls blocs dont le composant rend un titre h1 ou h2', () => {
		const sources = componentSources();
		const types = registry.REGISTRY.filter((entry) => !entry.container).map((entry) => entry.type);
		assert.ok(types.length > 20);
		for (const type of types) {
			const source = sources.get(type);
			assert.ok(source, `composant de ${type}`);
			assert.equal(registry.styleHasEffect(type, 'titre'), HEADING.test(source), type);
		}
	});

	it('« Accent » sur les seuls blocs qui ont un élément coloré par l’accent (Bouton et filet de l’Espace : B8-8)', () => {
		const sources = componentSources();
		const classes = accentClasses();
		assert.ok(classes.includes('yack-cms-kicker') && classes.includes('yack-cms-faq'), classes.join(', '));
		for (const entry of registry.REGISTRY.filter((candidate) => !candidate.container)) {
			const coloured = classes.some((name) => sources.get(entry.type)?.includes(name)) || ['bouton', 'espace'].includes(entry.type);
			assert.equal(registry.styleHasEffect(entry.type, 'accent'), coloured, entry.type);
		}
	});

	it('Balise et Alignement vertical gardent leurs blocs ; le serveur reste juge (toute propriété reste acceptée)', () => {
		for (const entry of registry.REGISTRY) {
			assert.equal(registry.styleHasEffect(entry.type, 'balise'), registry.TITLED_BLOCKS.includes(entry.type), entry.type);
			assert.equal(registry.styleHasEffect(entry.type, 'alignementVertical'), registry.TWO_COLUMN_BLOCKS.includes(entry.type), entry.type);
			for (const property of ['fond', 'hautEspace', 'gaucheEspace', 'largeur', 'masquerMobile', 'ancre'] as const) {
				assert.equal(registry.styleHasEffect(entry.type, property), true, `${entry.type} ${property}`);
			}
		}
	});

	it('les réglages sans effet restent dans l’éditeur, marqués « seulement si posés » (une valeur déjà posée reste visible et effaçable)', () => {
		const hidden = (type: string) => registry.editorStyleTraits(type).filter((trait) => trait.onlyWhenSet).map((trait) => trait.styleProperty);
		assert.deepEqual(hidden('texte-image'), ['accent']);
		assert.deepEqual(hidden('texte'), ['accent', 'titre']);
		assert.deepEqual(hidden('espace'), ['texte', 'titre', 'alignement']);
		assert.deepEqual(hidden('section'), []);
		for (const entry of registry.REGISTRY) {
			const shown = registry.editorStyleTraits(entry.type).filter((trait) => !trait.onlyWhenSet);
			assert.deepEqual(shown, registry.styleTraits(entry.type), entry.type);
		}
	});

	it('un conteneur garde les réglages de BlockType::CONTAINER_STYLE', () => {
		assert.deepEqual(styleTab('section'), ['fond', 'texte', 'hautEspace', 'droiteEspace', 'basEspace', 'gaucheEspace', 'largeur']);
	});

	it('l’espacement réunit les quatre côtés dans l’ordre du champ', () => {
		const spacing = registry.STYLE_TRAITS.find((trait) => trait.type === 'yack-spacing');
		assert.deepEqual(spacing?.styleProperties, SPACING_SIDES.map(({ property }) => property));
		assert.deepEqual([...registry.SPACING_PROPERTIES], SPACING_SIDES.map(({ property }) => property));
	});
});

describe('choix à pictogrammes (yack-icons)', () => {
	let server: ViteDevServer;
	let registry: typeof import('../src/lib/blocks/registry.ts');

	before(async () => {
		server = await createServer({
			root: fileURLToPath(new URL('../', import.meta.url)),
			logLevel: 'error',
			server: { middlewareMode: true, hmr: false, watch: null },
			appType: 'custom'
		});
		registry = (await server.ssrLoadModule('/src/lib/blocks/registry.ts')) as typeof registry;
	});
	after(() => server.close());

	it('Alignement, Largeur, Arrondi, Alignement vertical et Position du visuel', () => {
		const fields = [...registry.STYLE_TRAITS, registry.VERTICAL_ALIGNMENT_TRAIT, ...(registry.blockDefinition('texte-image')?.traits ?? [])];
		assert.deepEqual(
			fields.filter((trait) => trait.type === 'yack-icons').map((trait) => trait.name),
			['yb-largeur', 'yb-alignement', 'yb-arrondi', 'yb-alignementVertical', 'imagePosition']
		);
	});

	it('chaque choix a un nom et un pictogramme (les rayons : ceux de la charte)', () => {
		const fields = [...registry.STYLE_TRAITS, registry.VERTICAL_ALIGNMENT_TRAIT, ...(registry.blockDefinition('texte-image')?.traits ?? [])].filter(
			(trait) => trait.type === 'yack-icons'
		);
		for (const trait of fields) {
			if (trait.charte === 'radii') {
				assert.equal(trait.options, undefined);
				continue;
			}
			assert.ok((trait.options?.length ?? 0) >= 2, trait.name);
			for (const option of trait.options ?? []) {
				assert.ok(option.label.trim() !== '' && option.icon, `${trait.name} ${option.id}`);
			}
		}
		const position = registry.blockDefinition('texte-image')?.traits.find((trait) => trait.name === 'imagePosition');
		assert.deepEqual(position?.options?.map((option) => option.id), ['gauche', 'droite']);
		assert.equal(position?.styleProperty, undefined, 'une prop : toujours une valeur, pas de « Par défaut »');
	});

	it('les rayons de la charte ont chacun leur dessin', () => {
		const svelte = read('front/src/lib/editor/traits/YackIcons.svelte');
		const radii = /RADII = \[([\s\S]*?)\];/.exec(read('src/Yack/Content/Domain/Charte.php'))?.[1] ?? '';
		const keys = [...radii.matchAll(/^\s*'([a-z]+)' => /gm)].map((m) => m[1]);
		assert.deepEqual(keys, ['aucun', 'petit', 'moyen', 'grand']);
		for (const key of keys) assert.ok(svelte.includes(`'radius-${key}'`), key);
	});
});
