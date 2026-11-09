// Tests of the lists of the builder (src/lib/editor/traits/list.ts, LIST_TRAITS of the registry): same
// reading as the shop (parseFaq, parseLinks), and reading then writing every list of the fixtures
// (config/yack/fixtures_cms.yaml, read as a file: pages and templates) gives the same string. `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { after, before, describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createServer, type ViteDevServer } from 'vite';
import { parseFaq, parseLinks } from '../src/lib/blocks/parse.ts';
import {
	addItem,
	joinFields,
	LINE_BREAK_REFUSED,
	moveItem,
	parseList,
	PIPE_REFUSED,
	refusedInput,
	removeItem,
	serializeList,
	setField,
	splitLine
} from '../src/lib/editor/traits/list.ts';

const roundTrip = (value: string, count = 2): string => serializeList(parseList(value, count));
const fields = (value: string, count = 2): string[][] => parseList(value, count).items.map((item) => item.fields);

/** Prop of a block of the fixtures: block type, prop name, value as YAML gives it, line number. */
interface FixtureProp {
	type: string;
	prop: string;
	value: string;
	line: number;
}

/**
 * The props of the blocks of fixtures_cms.yaml (pages and templates), read with the YAML rules the
 * file uses: plain, '…' ('' = '), "…", and block scalars | (clip), |- (strip), |+ (keep).
 */
function fixtureProps(yaml: string): FixtureProp[] {
	const lines = yaml.split('\n');
	const props: FixtureProp[] = [];
	let type: string | null = null;
	let propsIndent: number | null = null;
	for (let i = 0; i < lines.length; i++) {
		const line = lines[i];
		const block = /^(\s*)-\s+type:\s*'?([a-z-]+)'?\s*$/.exec(line);
		if (block) {
			type = block[2];
			propsIndent = null;
			continue;
		}
		if (type === null) continue;
		if (/^\s+props:\s*$/.test(line)) {
			propsIndent = -1;
			continue;
		}
		if (propsIndent === null) continue;
		const prop = /^(\s+)([A-Za-z0-9]+):\s?(.*)$/.exec(line);
		if (!prop) continue;
		const indent = prop[1].length;
		if (propsIndent === -1) propsIndent = indent;
		if (indent !== propsIndent) {
			if (indent < propsIndent) propsIndent = null;
			continue;
		}
		const raw = prop[3];
		let value: string;
		const scalar = /^\|([+-]?)\s*$/.exec(raw);
		if (scalar) {
			const body: string[] = [];
			let blockIndent: number | null = null;
			while (i + 1 < lines.length) {
				const next = lines[i + 1];
				const nextIndent = next.length - next.trimStart().length;
				if (next.trim() !== '' && nextIndent <= indent) break;
				if (next.trim() !== '' && blockIndent === null) blockIndent = nextIndent;
				body.push(next.trim() === '' ? '' : next.slice(blockIndent ?? 0));
				i++;
			}
			let trailing = 0;
			while (body.length > 0 && body[body.length - 1] === '') {
				body.pop();
				trailing++;
			}
			const text = body.join('\n');
			value = scalar[1] === '-' || text === '' ? text : scalar[1] === '+' ? text + '\n'.repeat(trailing + 1) : text + '\n';
		} else if (raw.startsWith("'")) {
			assert.ok(raw.trimEnd().endsWith("'"), `line ${i + 1}: single-quoted value on one line`);
			value = raw.trimEnd().slice(1, -1).replaceAll("''", "'");
		} else if (raw.startsWith('"')) {
			value = JSON.parse(raw.trimEnd()) as string;
		} else {
			assert.ok(!/^[>&*!%@`]/.test(raw), `line ${i + 1}: YAML form not read by this test`);
			value = raw.trim();
		}
		props.push({ type, prop: prop[2], value, line: i + 1 });
	}
	return props;
}

const FIXTURES = readFileSync(new URL('../../config/yack/fixtures_cms.yaml', import.meta.url), 'utf8');

describe('splitLine', () => {
	it('reads a line as the shop does: split on « | », trimmed, the rest in the last column', () => {
		for (const line of ['Question ? | Réponse', 'Question ? | Réponse | suite', 'Seule', '  a  |  b  ', 'a|b|', 'a | |']) {
			const [question, answer] = splitLine(line, 2);
			assert.deepEqual(parseFaq(line), [{ question, answer }], line);
		}
		// The shop shows no element without a first column; the builder shows it, to fix or remove it.
		assert.deepEqual(splitLine(' | Réponse', 2), ['', 'Réponse']);
		assert.deepEqual(splitLine('|', 2), ['', '']);
	});

	it('gives empty columns when the line has fewer', () => {
		assert.deepEqual(splitLine('Libellé', 3), ['Libellé', '', '']);
		assert.deepEqual(splitLine('a | b | c | d', 3), ['a', 'b', 'c | d']);
	});
});

describe('joinFields', () => {
	it('writes « a | b », trimmed, without the empty last columns', () => {
		assert.equal(joinFields(['Question ?', 'Réponse']), 'Question ? | Réponse');
		assert.equal(joinFields(['  Question ?  ', '  Réponse ']), 'Question ? | Réponse');
		assert.equal(joinFields(['Question ?', '']), 'Question ?');
		assert.equal(joinFields(['', 'Réponse']), ' | Réponse');
	});
});

describe('parseList and serializeList', () => {
	it('gives back exactly what it read', () => {
		for (const value of [
			'',
			'\n',
			'   ',
			'Q ? | R',
			'Q ? | R\n',
			'Q ? | R\nQ2 ? | R2',
			'Q ? | R\r\nQ2 ? | R2\r\n',
			'Q ? | R\n\n\nQ2 ? | R2\n\n',
			'\nQ ? | R',
			'Q ?|R\n  Q2 ?   |   R2  \n',
			'Q ? | R | suite\nSeule\n',
			'|\n | R\nQ |\n',
			'Q ? | R\r\nmélangé | fins\nde | ligne'
		]) {
			assert.equal(roundTrip(value), value, JSON.stringify(value));
		}
	});

	it('reads the elements as the shop does (blank lines are no element)', () => {
		const value = 'Q ? | R | suite\n\n  \nSeule\r\nQ2|R2\n';
		assert.deepEqual(
			fields(value).map(([question, answer]) => ({ question, answer })),
			parseFaq(value)
		);
	});

	it('writes a changed element « a | b » and leaves the others as they were', () => {
		let list = parseList('Q1 ?|R1\nQ2 ?   |  R2\n', 2);
		list = setField(list, 0, 1, 'Nouvelle réponse');
		assert.equal(serializeList(list), 'Q1 ? | Nouvelle réponse\nQ2 ?   |  R2\n');
	});

	it('moves an element with its blank lines and keeps the end of the value', () => {
		const list = parseList('A | 1\nB | 2\n\nC | 3\n', 2);
		assert.equal(serializeList(moveItem(list, 2, 0)), '\nC | 3\nA | 1\nB | 2\n');
		assert.equal(serializeList(moveItem(list, 0, 2)), 'B | 2\n\nC | 3\nA | 1\n');
		assert.equal(serializeList(moveItem(parseList('A | 1\nB | 2', 2), 1, 0)), 'B | 2\nA | 1');
		assert.equal(serializeList(moveItem(parseList('A | 1\r\nB | 2\r\n', 2), 1, 0)), 'B | 2\r\nA | 1\r\n');
	});

	it('keeps the list as it is for a move out of the list', () => {
		const list = parseList('A | 1\nB | 2', 2);
		assert.equal(moveItem(list, 0, -1), list);
		assert.equal(moveItem(list, 1, 9), list);
		assert.equal(serializeList(moveItem(list, 0, 9)), 'B | 2\nA | 1');
	});

	it('adds an element written once a column is filled', () => {
		let list = addItem(parseList('A | 1\n', 2), 2);
		assert.equal(list.items.length, 2);
		assert.equal(serializeList(list), 'A | 1\n');
		list = setField(list, 1, 0, 'B');
		assert.equal(serializeList(list), 'A | 1\nB\n');
		list = setField(list, 1, 1, '2');
		assert.equal(serializeList(list), 'A | 1\nB | 2\n');
		assert.equal(serializeList(setField(addItem(parseList('', 2), 2), 0, 0, 'Seule')), 'Seule');
	});

	it('removes an element, and an element emptied in its fields', () => {
		const list = parseList('A | 1\nB | 2\nC | 3\n', 2);
		assert.equal(serializeList(removeItem(list, 1)), 'A | 1\nC | 3\n');
		assert.equal(serializeList(removeItem(list, 2)), 'A | 1\nB | 2\n');
		assert.equal(serializeList(removeItem(removeItem(removeItem(list, 0), 0), 0)), '');
		assert.equal(serializeList(setField(setField(list, 0, 0, ''), 0, 1, ' ')), 'B | 2\nC | 3\n');
	});

	it('gives the shop the edited list', () => {
		let list = parseList('Notice | /notice.pdf\nNos services | page:nos-services\n', 2);
		list = moveItem(list, 1, 0);
		list = setField(addItem(list, 2), 2, 0, 'SAV');
		list = setField(list, 2, 1, '/sav');
		assert.deepEqual(parseLinks(serializeList(list)), [
			{ label: 'Nos services', url: '/nos-services' },
			{ label: 'Notice', url: '/notice.pdf' },
			{ label: 'SAV', url: '/sav' }
		]);
	});
});

describe('refusedInput', () => {
	it('refuses a « | » typed or pasted', () => {
		assert.equal(refusedInput('Question', 'Question |'), PIPE_REFUSED);
		assert.equal(refusedInput('', 'a | b'), PIPE_REFUSED);
		assert.match(PIPE_REFUSED, /« \| »/);
	});

	it('keeps a « | » already in the value read from the page', () => {
		assert.equal(refusedInput('Réponse | suite', 'Réponse | suite.'), null);
		assert.equal(refusedInput('Réponse | suite', 'Réponse  suite'), null);
	});

	it('refuses a line break', () => {
		assert.equal(refusedInput('Réponse', 'Réponse\nsuite'), LINE_BREAK_REFUSED);
		assert.equal(refusedInput('Réponse', 'Réponse\r'), LINE_BREAK_REFUSED);
	});

	it('accepts any other text', () => {
		assert.equal(refusedInput('', 'Comment voir les prix ? 100 % / 2 €'), null);
	});
});

describe('lists of the registry and of the fixtures', () => {
	let server: ViteDevServer;
	let registry: typeof import('../src/lib/blocks/registry.ts');

	before(async () => {
		server = await createServer({
			root: fileURLToPath(new URL('../', import.meta.url)),
			cacheDir: join(tmpdir(), 'yack-front-list-test'),
			logLevel: 'error',
			server: { middlewareMode: true, hmr: false, watch: null },
			appType: 'custom'
		});
		registry = (await server.ssrLoadModule('/src/lib/blocks/registry.ts')) as typeof registry;
	});
	after(() => server.close());

	const listOf = (type: string, prop: string) => registry.blockDefinition(type)?.traits.find((trait) => trait.name === prop);

	it('edits the FAQ and the documents as lists, with their columns', () => {
		assert.deepEqual(
			Object.entries(registry.LIST_TRAITS).flatMap(([type, props]) => Object.keys(props ?? {}).map((prop) => `${type}.${prop}`)),
			['faq.items', 'documents.items']
		);
		assert.deepEqual(listOf('faq', 'items')?.columns?.map((column) => column.label), ['Question', 'Réponse']);
		assert.deepEqual(listOf('documents', 'items')?.columns?.map((column) => column.label), ['Libellé', 'Lien']);
		for (const [type, props] of Object.entries(registry.LIST_TRAITS)) {
			for (const prop of Object.keys(props ?? {})) {
				const trait = listOf(type, prop);
				assert.equal(trait?.type, 'yack-list', `${type}.${prop}`);
				assert.ok(prop in (registry.blockDefinition(type)?.defaults ?? {}), `${type}.${prop} is a prop of the block`);
			}
		}
	});

	it('reads then writes every list of the fixtures (pages and templates) as the same string', () => {
		const props = fixtureProps(FIXTURES);
		assert.ok(props.length > 100, `props read from the fixtures: ${props.length}`);
		const lists = props.filter(({ type, prop }) => registry.LIST_TRAITS[type as keyof typeof registry.LIST_TRAITS]?.[prop]);
		assert.ok(lists.length > 0, 'the fixtures contain lists');
		assert.ok(new Set(lists.map(({ type }) => type)).size === 2, 'FAQ and documents are both in the fixtures');
		for (const { type, prop, value, line } of lists) {
			const columns = listOf(type, prop)?.columns?.length ?? 0;
			assert.equal(serializeList(parseList(value, columns)), value, `${type}.${prop}, line ${line}`);
			assert.deepEqual(
				fields(value, columns).filter(([first]) => first !== '').map(([question, answer]) => ({ question, answer })),
				parseFaq(value),
				`${type}.${prop}, line ${line}: same elements as the shop`
			);
		}
		console.log(`# ${lists.length} lists of the fixtures read and written back`);
	});

	it('edits as a list every prop of the fixtures written in lines « a | b »', () => {
		for (const { type, prop, value, line } of fixtureProps(FIXTURES)) {
			if (!value.includes('|')) continue;
			assert.ok(listOf(type, prop)?.type === 'yack-list', `${type}.${prop}, line ${line}: « | » in the value, not edited as a list`);
		}
	});

	it('reads then writes the default lists of the registry as the same string', () => {
		for (const [type, props] of Object.entries(registry.LIST_TRAITS)) {
			for (const prop of Object.keys(props ?? {})) {
				const value = registry.blockDefinition(type)?.defaults[prop] ?? '';
				assert.ok(value.includes('|'), `${type}.${prop}`);
				assert.equal(roundTrip(value, listOf(type, prop)?.columns?.length), value);
			}
		}
	});
});
