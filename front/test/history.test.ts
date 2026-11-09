// Tests of the history of the builder (src/lib/editor/panels/history.ts): reading of the revisions API,
// labels in French in the local time of the browser, counts, values outside the charte that a Charte
// account could not save again (same rule as Content\Domain\CharteGuard, read as a file). `npm test`.
// Dates are built with the local constructor: the labels hold in any time zone.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
	authorLabel,
	blockCountLabel,
	charteWarning,
	dayLabel,
	describeDeviations,
	deviations,
	newDeviations,
	parseRevision,
	parseRevisionList,
	parseRevisionSummary,
	revisionDateLabel,
	revisionLine,
	statusLabel,
	timeLabel,
	versionCountLabel,
	versionLabel
} from '../src/lib/editor/panels/history.ts';

const root = new URL('../../', import.meta.url);
const NOW = new Date(2026, 10, 8, 16, 30);
const at = (...parts: [number, number, number, number, number]) => new Date(...parts).toISOString();

/** A line of GET /cms-pages/{slug}/revisions, as B4-1 sends it. */
const line = (overrides: Record<string, unknown> = {}) => ({
	id: 4,
	target: 'page',
	targetId: 'accueil',
	createdAt: at(2026, 10, 8, 14, 5),
	author: { firstName: 'Sophie', lastName: 'Martin', name: 'Sophie Martin' },
	status: 'published',
	title: 'Accueil',
	blockCount: 3,
	...overrides
});

describe('reading the revisions API', () => {
	it('reads a line of the list', () => {
		assert.deepStrictEqual(parseRevisionSummary(line()), {
			id: 4,
			target: 'page',
			createdAt: at(2026, 10, 8, 14, 5),
			author: 'Sophie Martin',
			status: 'published',
			title: 'Accueil',
			blockCount: 3
		});
	});

	it('reads a version of the fixtures (no author) and a template', () => {
		assert.strictEqual(parseRevisionSummary(line({ author: null }))?.author, null);
		assert.strictEqual(parseRevisionSummary(line({ target: 'template', targetId: '2', status: 'draft' }))?.target, 'template');
	});

	it('drops lines that are not revisions', () => {
		for (const bad of [null, 'x', [], line({ id: 0 }), line({ id: '4' }), line({ target: 'section' }), line({ createdAt: 'hier' })]) {
			assert.strictEqual(parseRevisionSummary(bad), null, JSON.stringify(bad));
		}
		assert.deepStrictEqual(
			parseRevisionList([line({ id: 3 }), { id: 'x' }, line({ id: 2 })]).map((revision) => revision.id),
			[3, 2]
		);
	});

	it('reads JSON-LD collections and gives an empty list for anything else', () => {
		assert.strictEqual(parseRevisionList({ 'hydra:member': [line()] }).length, 1);
		assert.strictEqual(parseRevisionList({ member: [line()] }).length, 1);
		assert.deepStrictEqual(parseRevisionList({ detail: 'Page introuvable.' }), []);
		assert.deepStrictEqual(parseRevisionList(null), []);
	});

	it('reads the blocks of a revision like those of a page (PHP [] for no props, no style)', () => {
		const revision = parseRevision({
			...line(),
			content: {
				title: 'Accueil',
				status: 'published',
				grapesData: null,
				blocks: [
					{ type: 'hero', props: { title: 'Bienvenue', audience: 'pro' }, style: [] },
					{ type: 'produit-galerie', props: [], style: { fond: '#FF7700', inconnu: 'x' } },
					{ type: 'cta', props: { title: 'Sans style', count: 3 } },
					{ props: {} }
				]
			}
		});
		assert.deepStrictEqual(revision?.blocks, [
			{ type: 'hero', props: { title: 'Bienvenue', audience: 'pro' }, style: {} },
			{ type: 'produit-galerie', props: {}, style: { fond: '#FF7700' } },
			{ type: 'cta', props: { title: 'Sans style' }, style: {} }
		]);
		assert.deepStrictEqual(parseRevision(line())?.blocks, []);
		assert.strictEqual(parseRevision({ content: { blocks: [] } }), null);
	});
});

describe('labels in French, local time', () => {
	it('writes the time as « 14 h 05 »', () => {
		assert.strictEqual(timeLabel(new Date(2026, 10, 8, 14, 5)), '14 h 05');
		assert.strictEqual(timeLabel(new Date(2026, 10, 8, 9, 0)), '9 h 00');
		assert.strictEqual(timeLabel(new Date(2026, 10, 8, 0, 7)), '0 h 07');
	});

	it('writes the day, with the year only when it is not the current one', () => {
		assert.strictEqual(dayLabel(new Date(2026, 10, 8), NOW), '8 novembre');
		assert.strictEqual(dayLabel(new Date(2026, 4, 1), NOW), '1er mai');
		assert.strictEqual(dayLabel(new Date(2025, 11, 30), NOW), '30 décembre 2025');
	});

	it('dates a line « Aujourd’hui », « Hier », or by its day', () => {
		assert.strictEqual(revisionDateLabel(at(2026, 10, 8, 14, 5), NOW), "Aujourd'hui 14 h 05");
		assert.strictEqual(revisionDateLabel(at(2026, 10, 8, 0, 0), NOW), "Aujourd'hui 0 h 00");
		assert.strictEqual(revisionDateLabel(at(2026, 10, 7, 23, 59), NOW), 'Hier 23 h 59');
		assert.strictEqual(revisionDateLabel(at(2026, 10, 6, 9, 30), NOW), '6 novembre 9 h 30');
		assert.strictEqual(revisionDateLabel(at(2025, 11, 31, 18, 0), NOW), '31 décembre 2025 18 h 00');
		// « Hier » across a month and a year.
		assert.strictEqual(revisionDateLabel(at(2026, 9, 31, 10, 0), new Date(2026, 10, 1, 8, 0)), 'Hier 10 h 00');
		assert.strictEqual(revisionDateLabel(at(2025, 11, 31, 10, 0), new Date(2026, 0, 1, 8, 0)), 'Hier 10 h 00');
		assert.strictEqual(revisionDateLabel('pas une date', NOW), '');
	});

	it('reads the offset of the server: the label is in the time of the browser', () => {
		const local = new Date(2026, 10, 8, 14, 5);
		const offset = -local.getTimezoneOffset();
		const sign = offset >= 0 ? '+' : '-';
		const pad = (n: number) => String(Math.floor(Math.abs(n))).padStart(2, '0');
		const iso = `2026-11-08T14:05:00${sign}${pad(offset / 60)}:${pad(offset % 60)}`;
		assert.strictEqual(revisionDateLabel(iso, NOW), "Aujourd'hui 14 h 05");
	});

	it('titles the banner « Version du 8 novembre, 14 h 05 »', () => {
		assert.strictEqual(versionLabel(at(2026, 10, 8, 14, 5), NOW), 'Version du 8 novembre, 14 h 05');
		assert.strictEqual(versionLabel(at(2025, 0, 1, 9, 0), NOW), 'Version du 1er janvier 2025, 9 h 00');
		assert.strictEqual(versionLabel('?', NOW), 'Version');
	});

	it('writes a line « Aujourd’hui 14 h 05, Sophie Martin, publiée »', () => {
		const summary = parseRevisionSummary(line())!;
		assert.strictEqual(revisionLine(summary, NOW), "Aujourd'hui 14 h 05, Sophie Martin, publiée");
		assert.strictEqual(revisionLine({ ...summary, author: null, status: 'draft' }, NOW), "Aujourd'hui 14 h 05, Version de démonstration, brouillon");
		assert.strictEqual(revisionLine({ ...summary, target: 'template' }, NOW), "Aujourd'hui 14 h 05, Sophie Martin, publié");
	});

	it('agrees the status with a page or a template', () => {
		assert.strictEqual(statusLabel('published', 'page'), 'publiée');
		assert.strictEqual(statusLabel('published', 'template'), 'publié');
		assert.strictEqual(statusLabel('draft', 'page'), 'brouillon');
		assert.strictEqual(statusLabel('draft', 'template'), 'brouillon');
		assert.strictEqual(authorLabel(null), 'Version de démonstration');
	});
});

describe('counts', () => {
	it('counts the blocks of a version', () => {
		assert.strictEqual(blockCountLabel(0), 'aucun bloc');
		assert.strictEqual(blockCountLabel(1), '1 bloc');
		assert.strictEqual(blockCountLabel(8), '8 blocs');
	});

	it('counts the versions', () => {
		assert.strictEqual(versionCountLabel(0), 'Aucune version enregistrée');
		assert.strictEqual(versionCountLabel(1), '1 version');
		assert.strictEqual(versionCountLabel(50), '50 versions');
	});
});

describe('values outside the charte (CharteGuard)', () => {
	const orange = { type: 'texte-image', props: {}, style: { fond: '#ff7700', alignement: 'centre' } };
	const teal = { type: 'cta', props: {}, style: { fond: 'teal', titre: '60px' } };

	it('lists the free values of the blocks, in block order, charte values left out', () => {
		assert.deepStrictEqual(deviations([{ style: [] }, orange, teal, { style: { hautEspace: 'xl', basEspace: '120px' } }]), [
			{ block: 2, property: 'fond', value: '#ff7700' },
			{ block: 3, property: 'titre', value: '60px' },
			{ block: 4, property: 'basEspace', value: '120px' }
		]);
	});

	it('finds nothing new when the stored content holds the same free values, wherever they are', () => {
		assert.deepStrictEqual(newDeviations([orange], [teal, { style: { fond: '#FF7700' } }]), []);
		assert.deepStrictEqual(newDeviations([], [orange]), []);
		assert.deepStrictEqual(newDeviations([teal], [{ style: { titre: '60px' } }]), []);
	});

	it('finds a free value removed since, the same value on another property, an extra occurrence', () => {
		assert.deepStrictEqual(newDeviations([orange, teal], [teal]), [{ block: 1, property: 'fond', value: '#ff7700' }]);
		assert.deepStrictEqual(newDeviations([{ style: { texte: '#ff7700' } }], [orange]), [{ block: 1, property: 'texte', value: '#ff7700' }]);
		assert.deepStrictEqual(newDeviations([orange, orange], [orange]), [{ block: 2, property: 'fond', value: '#ff7700' }]);
	});

	it('describes them and warns a Charte account only', () => {
		const list = newDeviations([orange, teal], []);
		assert.strictEqual(describeDeviations(list), 'bloc n°1 : Fond #ff7700 ; bloc n°2 : Taille du titre 60px');
		assert.strictEqual(charteWarning('libre', list), null);
		assert.strictEqual(charteWarning('libre_avertissement', list), null);
		assert.strictEqual(charteWarning('charte', []), null);
		assert.match(charteWarning('charte', list) ?? '', /^Cette version contient des valeurs hors charte retirées depuis \(bloc n°1 : Fond #ff7700 ; bloc n°2 : Taille du titre 60px\)\. Votre compte \(niveau Charte\) ne pourra pas l'enregistrer telle quelle/);
		assert.match(charteWarning('charte', list.slice(0, 1)) ?? '', /une valeur hors charte retirée depuis .*remplacez cette valeur par une valeur de la charte/);
	});

	it('compares like CharteGuard: multiset of (property, value), case ignored', () => {
		const guard = readFileSync(new URL('src/Yack/Content/Domain/CharteGuard.php', root), 'utf8');
		assert.match(guard, /\$deviation->property \. '=' \. strtolower\(\$deviation->value\)/);
		assert.match(guard, /--\$available\[\$key\]/);
	});
});

describe('texts', () => {
	it('has no long dash nor en dash in the files of the history', () => {
		for (const file of ['history.ts', 'History.svelte', 'revisionCanvas.ts']) {
			const source = readFileSync(new URL(`front/src/lib/editor/panels/${file}`, root), 'utf8');
			assert.doesNotMatch(source, /[\u2013\u2014]/, file);
		}
	});
});
