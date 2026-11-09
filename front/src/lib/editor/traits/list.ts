// Lists of a block (FAQ, documents: D9), stored as one line per element, columns separated by « | »,
// edited element by element by YackList.svelte. Same reading as the shop (#lib/blocks/parse.ts
// parseFaq): a line is split on « | », each column trimmed, the last column keeps the rest of the line;
// a blank line is no element. Pure, tested by test/list.test.ts.
//
// Writing gives back exactly what was read: an element left untouched keeps its line as it was
// (spaces, a « | » in its last column), blank lines and line breaks stay where they were. A changed or
// new element is written « a | b » (one space around the « | », empty last columns left out); a new
// element not filled yet, or an element whose columns were all emptied, is not written.

/** Column of a list (one field of each card). */
export interface ListColumn {
	/** Label of the field (« Question », « Réponse »). */
	label: string;
	/** Several lines in the field (long text); the stored value stays on one line. */
	multiline?: boolean;
	placeholder?: string;
	/** The shop does not show an element whose column is empty. */
	required?: boolean;
	/** The column is a link (resolveLink): the shop does not show an element whose link it refuses. */
	link?: boolean;
}

/** Line of the stored value that is no element (blank), kept as it was. */
interface BlankLine {
	text: string;
	/** Line break after it. */
	eol: string;
}

export interface ListItem {
	/** Key of the card (stable while the list is edited). */
	id: number;
	/** One value per column. */
	fields: string[];
	/** Line read from the stored value, null for a new element. */
	raw: string | null;
	/** Line break after the line ('' for a new element: the list's own). */
	eol: string;
	/** Blank lines read just before the element (they move with it). */
	before: BlankLine[];
}

export interface ListValue {
	items: ListItem[];
	/** Blank lines after the last element. */
	tail: BlankLine[];
	/** Line break between two lines (the first one read, « \n » by default). */
	eol: string;
	/** Line break at the very end of the value ('' when the value does not end with one). */
	end: string;
}

let nextId = 0;

/** Columns of a line, as the shop reads it: split on « | », trimmed, the rest in the last column. */
export function splitLine(line: string, count: number): string[] {
	const parts = line.split('|');
	const fields: string[] = [];
	for (let i = 0; i < count; i++) {
		const part = i === count - 1 ? parts.slice(i).join('|') : (parts[i] ?? '');
		fields.push(part.trim());
	}
	return fields;
}

/** Line of an element written from its columns: « a | b », empty last columns left out. */
export function joinFields(fields: string[]): string {
	const kept = [...fields];
	while (kept.length > 0 && kept[kept.length - 1].trim() === '') kept.pop();
	return kept.map((field) => field.trim()).join(' | ');
}

/** Reads a stored list (`count` columns). */
export function parseList(value: string | undefined, count: number): ListValue {
	const source = value ?? '';
	// ['line', '\n', 'line', '\r\n', 'last'] : lines and the breaks between them.
	const pieces = source.split(/(\r?\n)/);
	const lines: BlankLine[] = [];
	for (let i = 0; i < pieces.length; i += 2) lines.push({ text: pieces[i] ?? '', eol: pieces[i + 1] ?? '' });
	let end = '';
	// « a\nb\n » ends with a line break, not with an empty line.
	if (lines.length > 1 && lines[lines.length - 1].text === '') {
		lines.pop();
		end = lines[lines.length - 1].eol;
	} else if (lines.length === 1 && lines[0].text === '') {
		lines.pop();
	}
	const eol = lines.find((line) => line.eol !== '')?.eol ?? (end || '\n');

	const items: ListItem[] = [];
	let pending: BlankLine[] = [];
	lines.forEach((line, index) => {
		// The last line's break is the value's own end, not the element's.
		const lineEol = index === lines.length - 1 ? '' : line.eol;
		if (line.text.trim() === '') {
			pending.push({ text: line.text, eol: lineEol });
			return;
		}
		items.push({ id: ++nextId, fields: splitLine(line.text, count), raw: line.text, eol: lineEol, before: pending });
		pending = [];
	});
	return { items, tail: pending, eol, end };
}

/** Text of an element: its line as read while its columns are unchanged, else « a | b ». */
function itemLine(item: ListItem): string {
	if (item.raw !== null) {
		const read = splitLine(item.raw, item.fields.length);
		if (read.every((field, i) => field === item.fields[i])) return item.raw;
	}
	return joinFields(item.fields);
}

/** True when every column of the element is empty (it is not written). */
export function isEmptyItem(item: ListItem): boolean {
	return item.fields.every((field) => field.trim() === '');
}

/** Stored value of the list. */
export function serializeList(list: ListValue): string {
	const lines: BlankLine[] = [];
	for (const item of list.items) {
		const text = itemLine(item);
		// A line read as it is stays (even « | » alone); an element emptied here or never filled goes.
		if (text !== item.raw && isEmptyItem(item)) continue;
		lines.push(...item.before, { text, eol: item.eol });
	}
	lines.push(...list.tail);
	if (lines.length === 0) return '';
	return lines.map((line, index) => line.text + (index === lines.length - 1 ? list.end : line.eol || list.eol)).join('');
}

/** A new element (empty columns): shown as a card, written once a column is filled. */
export function addItem(list: ListValue, count: number): ListValue {
	return { ...list, items: [...list.items, { id: ++nextId, fields: Array.from({ length: count }, () => ''), raw: null, eol: '', before: [] }] };
}

export function removeItem(list: ListValue, index: number): ListValue {
	return { ...list, items: list.items.filter((_, i) => i !== index) };
}

/** Moves the element at `from` to `to` (both clamped to the list). */
export function moveItem(list: ListValue, from: number, to: number): ListValue {
	const target = Math.max(0, Math.min(list.items.length - 1, to));
	if (from === target || from < 0 || from >= list.items.length) return list;
	const items = [...list.items];
	const [moved] = items.splice(from, 1);
	items.splice(target, 0, moved);
	return { ...list, items };
}

export function setField(list: ListValue, index: number, column: number, value: string): ListValue {
	return {
		...list,
		items: list.items.map((item, i) => (i === index ? { ...item, fields: item.fields.map((field, c) => (c === column ? value : field)) } : item))
	};
}

export const PIPE_REFUSED = 'Le caractère « | » sépare les colonnes de la liste : il ne peut pas être saisi dans un champ.';
export const LINE_BREAK_REFUSED = 'Un élément tient sur une seule ligne : le retour à la ligne n’est pas possible dans ce champ.';

const count = (text: string, char: string): number => text.split(char).length - 1;

/**
 * Message when a field may not take `next` (it then keeps `previous`), null when it may: no « | »
 * typed or pasted (one already in a value read from the page may stay), no line break.
 */
export function refusedInput(previous: string, next: string): string | null {
	if (/[\r\n]/.test(next)) return LINE_BREAK_REFUSED;
	if (count(next, '|') > count(previous, '|')) return PIPE_REFUSED;
	return null;
}
