// Settings of the image of a block (src/lib/blocks/image.ts, D7): the choices match the PHP source
// (BlockType::IMAGE_FITS, IMAGE_FOCUSES, imageProps()) and the SCSS partial, the registry edits every image
// prop with the media library, and the server rendering of the blocks carries the settings (svelte/server
// through Vite, like the app). `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { after, before, describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import type { Component } from 'svelte';
import { createServer, type ViteDevServer } from 'vite';
import { IMAGE_FITS, IMAGE_FOCUSES, imageSettingNames, imageView } from '../src/lib/blocks/image.ts';

const root = new URL('../../', import.meta.url);
const read = (path: string): string => readFileSync(new URL(path, root), 'utf8');
const BLOCK_TYPE = read('src/Yack/Content/Domain/BlockType.php');
const SCSS = read('assets/shop/styles/_cms-images.scss');

/** Strings of a PHP `public const NAME = ['a', 'b'];`. */
function phpList(name: string): string[] {
	const match = new RegExp(`public const ${name} = \\[([^\\]]*)\\];`).exec(BLOCK_TYPE);
	assert.ok(match, `constante ${name} introuvable`);
	return [...match[1].matchAll(/'([^']*)'/g)].map((m) => m[1]);
}

describe('imageView', () => {
	it('gives the alternative text and the classes of the settings', () => {
		assert.deepEqual(imageView({ imageAlt: 'Salon chauffé', imageFit: 'couvrir', imageFocus: 'haut' }, 'image'), {
			alt: 'Salon chauffé',
			class: 'yack-cms-img--couvrir yack-cms-img--haut'
		});
		assert.deepEqual(imageView({ image3Fit: 'contenir' }, 'image3', 'yack-cms-tile__image'), { alt: '', class: 'yack-cms-tile__image yack-cms-img--contenir' });
	});

	it('keeps the markup of the block without settings', () => {
		assert.deepEqual(imageView({}, 'image'), { alt: '', class: undefined });
		assert.deepEqual(imageView({ imageAlt: '', imageFit: '', imageFocus: '' }, 'image'), { alt: '', class: undefined });
		assert.deepEqual(imageView({}, 'image1', 'yack-cms-tile__image'), { alt: '', class: 'yack-cms-tile__image' });
	});

	it('ignores a value outside the choices and keeps the alternative text on one line', () => {
		assert.deepEqual(imageView({ imageFit: 'cover;color:red', imageFocus: 'gauche', imageAlt: '  Deux\nlignes  ' }, 'image'), { alt: 'Deux lignes', class: undefined });
	});

	it('names the settings of an image prop as BlockType::imageSettingProps()', () => {
		assert.deepEqual(imageSettingNames('image2'), { alt: 'image2Alt', fit: 'image2Fit', focus: 'image2Focus' });
		assert.match(BLOCK_TYPE, /\$settings\[\$name \. 'Alt'\] = null;/);
		assert.match(BLOCK_TYPE, /\$settings\[\$name \. 'Fit'\] = self::IMAGE_FITS;/);
		assert.match(BLOCK_TYPE, /\$settings\[\$name \. 'Focus'\] = self::IMAGE_FOCUSES;/);
	});

	it('offers the choices of BlockType, each with its rule in the SCSS', () => {
		assert.deepEqual(['', ...IMAGE_FITS], phpList('IMAGE_FITS'));
		assert.deepEqual(['', ...IMAGE_FOCUSES], phpList('IMAGE_FOCUSES'));
		for (const value of [...IMAGE_FITS, ...IMAGE_FOCUSES]) assert.match(SCSS, new RegExp(`^img\\.yack-cms-img--${value} \\{`, 'm'), value);
	});
});

describe('image fields of the registry and rendering of the blocks', () => {
	let server: ViteDevServer;
	let registry: typeof import('../src/lib/blocks/registry.ts');
	let html: (block: Record<string, unknown>) => string;

	before(async () => {
		server = await createServer({
			root: fileURLToPath(new URL('../', import.meta.url)),
			logLevel: 'error',
			server: { middlewareMode: true, hmr: false, watch: null },
			appType: 'custom'
		});
		registry = (await server.ssrLoadModule('/src/lib/blocks/registry.ts')) as typeof registry;
		const { render } = (await server.ssrLoadModule('svelte/server')) as typeof import('svelte/server');
		const BlockRenderer = (await server.ssrLoadModule('/src/lib/blocks/BlockRenderer.svelte')).default as Component<{ block: unknown }>;
		const { yackContextMap } = (await server.ssrLoadModule('/src/lib/blocks/context.ts')) as typeof import('../src/lib/blocks/context.ts');
		const { VISITOR } = (await server.ssrLoadModule('/src/lib/types.ts')) as typeof import('../src/lib/types.ts');
		const context = yackContextMap({ viewer: VISITOR, catalog: { products: {}, taxons: {} }, editor: false });
		html = (block) => render(BlockRenderer, { props: { block }, context }).body.replace(/<!--[^>]*-->/g, '');
	});

	after(async () => {
		await server?.close();
	});

	it('edits the image props of BlockType::imageProps() with the media library, and only them', () => {
		assert.match(BLOCK_TYPE, /self::Hero, self::TextImage => \['image'\],/);
		assert.match(BLOCK_TYPE, /self::Tiles => array_keys\(self::slots\(\['image' => null\], self::TILE_SLOTS\)\),/);
		assert.match(BLOCK_TYPE, /self::Support => array_keys\(self::slots\(\['image' => null\], self::SUPPORT_SLOTS\)\),/);
		assert.match(BLOCK_TYPE, /public const TILE_SLOTS = 5;/);
		assert.match(BLOCK_TYPE, /public const SUPPORT_SLOTS = 3;/);
		// Read before the deepEqual, which narrows its argument to the literal.
		const imageProps: Partial<Record<string, string[]>> = registry.IMAGE_PROPS;
		assert.deepEqual({ ...imageProps }, {
			hero: ['image'],
			'texte-image': ['image'],
			tuiles: ['image1', 'image2', 'image3', 'image4', 'image5'],
			accompagnement: ['image1', 'image2', 'image3']
		});
		for (const entry of registry.REGISTRY) {
			const fields = entry.traits.filter((trait) => trait.type === 'yack-image').map((trait) => trait.name);
			assert.deepEqual(fields, imageProps[entry.type] ?? [], entry.type);
			for (const prop of fields) {
				const names = imageSettingNames(prop);
				assert.equal(entry.defaults[names.alt], '', `${entry.type} ${names.alt}`);
				assert.equal(entry.defaults[names.fit], '', `${entry.type} ${names.fit}`);
				assert.equal(entry.defaults[names.focus], '', `${entry.type} ${names.focus}`);
			}
		}
	});

	it('says which image fields accept no image (« Retirer »)', () => {
		const field = (type: string, name: string) => registry.blockDefinition(type)?.traits.find((trait) => trait.name === name);
		assert.equal(field('texte-image', 'image')?.options?.some((option) => option.id === ''), false);
		assert.equal(field('hero', 'image')?.options?.some((option) => option.id === ''), true);
		assert.equal(field('tuiles', 'image4')?.options?.some((option) => option.id === ''), true);
	});

	it('renders the alternative text and the classes on the image of each block', () => {
		const text = html({ type: 'texte-image', props: { title: 'T', text: 'x', image: 'yack/cms/pac-split.png', imageAlt: 'PAC split en façade', imageFit: 'couvrir', imageFocus: 'bas' } });
		assert.match(text, /<img src="\/yack\/cms\/pac-split\.png" alt="PAC split en façade" loading="lazy" class="yack-cms-img--couvrir yack-cms-img--bas"\/>/);
		const hero = html({ type: 'hero', props: { title: 'T', theme: 'photo', image: 'yack/cms/hero.jpg', imageAlt: 'Salon', imageFocus: 'haut' } });
		assert.match(hero, /<span class="yack-cms-hero__bg"><img src="\/yack\/cms\/hero\.jpg" alt="Salon" class="yack-cms-img--haut"\/><\/span>/);
		const tiles = html({ type: 'tuiles', props: { label2: 'Air/air', image2: 'yack/cms/pac-split.png', image2Fit: 'contenir', image2Alt: 'Split' } });
		assert.match(tiles, /<img class="yack-cms-tile__image yack-cms-img--contenir" src="\/yack\/cms\/pac-split\.png" alt="Split" loading="lazy"\/>/);
		const support = html({ type: 'accompagnement', props: { heading1: 'Groupe', image1: 'yack/logo-yack.png', image1Alt: 'Logo Yack' } });
		assert.match(support, /<img src="\/yack\/logo-yack\.png" alt="Logo Yack" loading="lazy"\/>/);
	});

	it('renders an image without settings as before (alt="", no class)', () => {
		assert.match(html({ type: 'texte-image', props: { image: 'yack/cms/pac-split.png', imageAlt: '', imageFit: '', imageFocus: '' } }), /<img src="\/yack\/cms\/pac-split\.png" alt="" loading="lazy"\/>/);
		assert.match(html({ type: 'tuiles', props: { label1: 'A', image1: 'yack/cms/gainable.png' } }), /<img class="yack-cms-tile__image" src="\/yack\/cms\/gainable\.png" alt="" loading="lazy"\/>/);
	});
});
