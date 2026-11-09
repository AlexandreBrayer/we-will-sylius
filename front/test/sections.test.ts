// Tests of the reusable sections of the builder (src/lib/editor/panels/sections.ts): reading of the sections
// API, the name rule (same as Content\Domain\CmsSectionName, read as a file), what a Charte account is told
// before saving or inserting a value outside the charte (same rule as CharteGuard for new content), and the
// French labels of the palette. `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
	cleanSectionName,
	deleteQuestion,
	holdsFreeValues,
	insertedMessage,
	insertWarning,
	parseSection,
	parseSectionList,
	saveIssue,
	savedMessage,
	SECTION_BLOCKS_MAX,
	SECTION_MESSAGES,
	SECTION_NAME_MAX,
	sectionDetail,
	sectionNameIssue
} from '../src/lib/editor/panels/sections.ts';

const root = new URL('../../', import.meta.url);
const php = (path: string) => readFileSync(new URL(path, root), 'utf8');

/** A section of GET /cms-sections, as Sylius sends it (empty style `[]`, props filled in). */
const section = (overrides: Record<string, unknown> = {}) => ({
	id: 3,
	name: 'Bandeau promo',
	blocks: [
		{ type: 'cta', props: { title: 'Appel', theme: 'magenta' }, style: [] },
		{ type: 'texte-image', props: { title: 'Orange', image: 'yack/cms/hero.jpg' }, style: { fond: '#ff7700', alignement: 'centre' } }
	],
	blockCount: 2,
	author: 'Sophie Martin',
	createdAt: '2026-11-08T14:05:00+00:00',
	updatedAt: '2026-11-08T14:05:00+00:00',
	...overrides
});

const orange = { type: 'texte-image', props: {}, style: { fond: '#ff7700' } };
const teal = { type: 'texte-image', props: {}, style: { fond: 'teal', titre: 'xl' } };

describe('reading the sections API', () => {
	it('reads a section with its blocks (PHP [] style read as {})', () => {
		assert.deepStrictEqual(parseSection(section()), {
			id: 3,
			name: 'Bandeau promo',
			blocks: [
				{ type: 'cta', props: { title: 'Appel', theme: 'magenta' }, style: {} },
				{ type: 'texte-image', props: { title: 'Orange', image: 'yack/cms/hero.jpg' }, style: { fond: '#ff7700', alignement: 'centre' } }
			],
			author: 'Sophie Martin',
			createdAt: '2026-11-08T14:05:00+00:00',
			updatedAt: '2026-11-08T14:05:00+00:00'
		});
	});

	it('keeps only text props and known style properties', () => {
		const read = parseSection(section({ blocks: [{ type: 'cta', props: { title: 'x', count: 3 }, style: { fond: 'teal', css: 'color:red', titre: 60 } }] }));
		assert.deepStrictEqual(read?.blocks, [{ type: 'cta', props: { title: 'x' }, style: { fond: 'teal' } }]);
	});

	it('reads a section without author', () => {
		assert.strictEqual(parseSection(section({ author: null }))?.author, null);
		assert.strictEqual(parseSection(section({ author: '  ' }))?.author, null);
	});

	it('drops what is not a section', () => {
		for (const bad of [null, 'x', [], section({ id: 0 }), section({ id: '3' }), section({ name: ' ' }), section({ blocks: [] }), section({ blocks: [{ props: {} }] })]) {
			assert.strictEqual(parseSection(bad), null, JSON.stringify(bad));
		}
		assert.deepStrictEqual(
			parseSectionList([section({ id: 1 }), { id: 'x' }, section({ id: 2 })]).map((read) => read.id),
			[1, 2]
		);
	});

	it('reads JSON-LD collections and gives an empty list for anything else', () => {
		assert.strictEqual(parseSectionList({ 'hydra:member': [section()] }).length, 1);
		assert.strictEqual(parseSectionList({ member: [section()] }).length, 1);
		assert.deepStrictEqual(parseSectionList({ detail: 'Not Found' }), []);
		assert.deepStrictEqual(parseSectionList(undefined), []);
	});
});

describe('name of a section (CmsSectionName)', () => {
	it('is one clean line', () => {
		assert.strictEqual(cleanSectionName('  Bandeau  promo \n'), 'Bandeau promo');
		assert.strictEqual(cleanSectionName('Deux\nlignes\tet\r\nplus'), 'Deux lignes et plus');
		assert.strictEqual(cleanSectionName('Offre ​PAC fin\u0007'), 'Offre PAC fin');
	});

	it('is refused when empty or longer than 120 characters, with the messages of Sylius', () => {
		assert.strictEqual(sectionNameIssue(' \n\t '), 'Donnez un nom à la section.');
		assert.strictEqual(sectionNameIssue('é'.repeat(121)), 'Le nom de la section fait au plus 120 caractères.');
		assert.strictEqual(sectionNameIssue(` ${'é'.repeat(120)}\n`), null);
		assert.strictEqual(sectionNameIssue('Bandeau'), null);
	});

	it('follows the rule and the bounds of Sylius', () => {
		const name = php('src/Yack/Content/Domain/CmsSectionName.php');
		assert.match(name, new RegExp(`public const MAX = ${SECTION_NAME_MAX};`));
		assert.match(name, /preg_replace\('\/\[\\p\{Cc\}\\p\{Cf\}\\p\{Z\}\]\+\/u', ' '/);
		assert.ok(name.includes(SECTION_MESSAGES.nameMissing));
		assert.ok(name.includes('Le nom de la section fait au plus %d caractères.'));
		assert.match(php('src/Yack/Content/Domain/PageBlocks.php'), new RegExp(`public const MAX_BLOCKS = ${SECTION_BLOCKS_MAX};`));
		assert.ok(php('src/Yack/Content/Application/CreateCmsSection.php').includes('CharteGuard::assertAllowed($level, $normalized);'));
	});
});

describe('saving the selection as a section', () => {
	it('needs a selected block', () => {
		assert.strictEqual(saveIssue([], [], 'libre'), SECTION_MESSAGES.noBlock);
	});

	it('refuses the blocks bound to the template, at every level', () => {
		assert.strictEqual(
			saveIssue([teal], ['Galerie produit'], 'libre'),
			'Galerie produit : ce bloc est lié à la fiche du template et ne s’enregistre pas comme section. Seuls les blocs Yack libres le peuvent.'
		);
		assert.match(saveIssue([], ['Galerie produit', 'Achat'], 'libre') ?? '', /^Galerie produit, Achat : ces blocs sont liés à la fiche du template et ne s’enregistrent pas/);
	});

	it('refuses more blocks than a page holds', () => {
		assert.strictEqual(saveIssue(Array(SECTION_BLOCKS_MAX + 1).fill(teal), [], 'libre'), SECTION_MESSAGES.tooManyBlocks);
		assert.strictEqual(saveIssue(Array(SECTION_BLOCKS_MAX).fill(teal), [], 'libre'), null);
	});

	it('counts the layout containers and their content against the limit, as Sylius', () => {
		const nested = (count: number) => [{ type: 'section', props: {}, children: [{ type: 'colonnes', props: { ratio: '50-50' }, columns: [Array(count).fill(teal), []] }] }];
		assert.strictEqual(saveIssue(nested(SECTION_BLOCKS_MAX - 2), [], 'libre'), null);
		assert.strictEqual(saveIssue(nested(SECTION_BLOCKS_MAX - 1), [], 'libre'), SECTION_MESSAGES.tooManyBlocks);
	});

	it('tells a Charte account where a nested value outside the charte is', () => {
		const section = { type: 'section', props: {}, style: {}, children: [teal, { type: 'colonnes', props: { ratio: '50-50' }, columns: [[], [orange]] }] };
		assert.strictEqual(
			saveIssue([section], [], 'charte'),
			'Ces blocs portent une valeur hors charte (section 1 › colonnes 2 › colonne 2 › bloc 1 : Fond #ff7700) : votre compte (niveau Charte) ne peut pas les enregistrer comme section. Remplacez d’abord cette valeur par une valeur de la charte (onglet Style).'
		);
	});

	it('tells a Charte account that a value outside the charte cannot be saved', () => {
		assert.strictEqual(
			saveIssue([orange], [], 'charte'),
			'Ce bloc porte une valeur hors charte (Fond #ff7700) : votre compte (niveau Charte) ne peut pas l’enregistrer comme section. Remplacez d’abord cette valeur par une valeur de la charte (onglet Style).'
		);
		assert.strictEqual(
			saveIssue([teal, { type: 'cta', props: {}, style: { fond: '#ff7700', titre: '60px' } }], [], 'charte'),
			'Ces blocs portent des valeurs hors charte (bloc n°2 : Fond #ff7700 ; bloc n°2 : Taille du titre 60px) : votre compte (niveau Charte) ne peut pas les enregistrer comme section. Remplacez d’abord ces valeurs par des valeurs de la charte (onglet Style).'
		);
	});

	it('lets the other levels save a value outside the charte, and everyone save the charte', () => {
		assert.strictEqual(saveIssue([orange], [], 'libre'), null);
		assert.strictEqual(saveIssue([orange], [], 'libre_avertissement'), null);
		assert.strictEqual(saveIssue([teal], [], 'charte'), null);
	});
});

describe('inserting a section', () => {
	const promo = parseSection(section())!;
	const charte = parseSection(section({ name: 'Turquoise', blocks: [teal] }))!;

	it('warns a Charte account before inserting a value outside the charte', () => {
		assert.strictEqual(
			insertWarning(promo, 'charte'),
			'La section « Bandeau promo » contient une valeur hors charte (bloc n°2 : Fond #ff7700). Votre compte (niveau Charte) ne pourra pas enregistrer la page tant qu’elle y reste : après l’insertion, remplacez-la par une valeur de la charte (onglet Style), ou annulez l’insertion (Ctrl+Z).'
		);
		assert.match(
			insertWarning({ name: 'Deux', blocks: [{ type: 'cta', props: {}, style: { fond: '#ff7700', texte: '#000001' } }] }, 'charte') ?? '',
			/des valeurs hors charte \(Fond #ff7700, Texte #000001\).*tant qu’elles y restent : après l’insertion, remplacez-les par des valeurs/
		);
	});

	it('does not warn the other levels nor a section of the charte', () => {
		assert.strictEqual(insertWarning(promo, 'libre'), null);
		assert.strictEqual(insertWarning(promo, 'libre_avertissement'), null);
		assert.strictEqual(insertWarning(charte, 'charte'), null);
	});

	it('says after the insertion that the blocks are copies, or repeats the warning', () => {
		assert.deepStrictEqual(insertedMessage(promo, 'libre'), {
			kind: 'ok',
			text: 'Section « Bandeau promo » insérée : ses blocs sont des copies, les modifier ne change pas la section (Ctrl+Z annule l’insertion).'
		});
		const warned = insertedMessage(promo, 'charte');
		assert.strictEqual(warned.kind, 'error');
		assert.ok(warned.text.startsWith('Section insérée. La section « Bandeau promo » contient une valeur hors charte'));
	});

	it('marks the sections that hold a value outside the charte', () => {
		assert.strictEqual(holdsFreeValues(promo.blocks), true);
		assert.strictEqual(holdsFreeValues(charte.blocks), false);
		assert.strictEqual(holdsFreeValues([{ style: [] }, {}]), false);
		assert.strictEqual(holdsFreeValues([{ type: 'section', children: [{ type: 'colonnes', columns: [[teal], [orange]] }] }]), true);
	});

	it('reads a saved section with its layout containers', () => {
		const read = parseSection(section({ blocks: [{ type: 'section', props: [], style: [], children: [{ type: 'colonnes', props: { ratio: '50-50' }, style: [], columns: [[{ type: 'faq', props: [], style: [] }], []] }] }] }));
		assert.deepStrictEqual(read?.blocks, [
			{ type: 'section', props: {}, style: {}, children: [{ type: 'colonnes', props: { ratio: '50-50' }, style: {}, columns: [[{ type: 'faq', props: {}, style: {} }], []] }] }
		]);
		assert.strictEqual(sectionDetail({ blocks: read?.blocks ?? [], author: null }), '3 blocs');
	});
});

describe('labels of the palette', () => {
	it('gives the number of blocks and the author', () => {
		assert.strictEqual(sectionDetail({ blocks: [teal, orange], author: 'Sophie Martin' }), '2 blocs · Sophie Martin');
		assert.strictEqual(sectionDetail({ blocks: [teal], author: null }), '1 bloc');
	});

	it('confirms a save and asks before a deletion', () => {
		assert.strictEqual(savedMessage('Bandeau'), 'Section « Bandeau » enregistrée : elle est dans « Mes sections » (onglet Blocs).');
		assert.strictEqual(deleteQuestion('Bandeau'), 'Supprimer la section « Bandeau » ? Les pages où elle a été insérée ne changent pas.');
	});

	it('writes no long dash in the section files', () => {
		for (const file of ['sections.ts', 'sectionCanvas.ts', 'SectionPalette.svelte', 'SaveSection.svelte']) {
			assert.doesNotMatch(readFileSync(new URL(`front/src/lib/editor/panels/${file}`, root), 'utf8'), /[\u2013\u2014]/, file);
		}
	});
});
