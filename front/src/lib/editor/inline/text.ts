// Rules of the texts typed in place in the builder canvas (D8), without the DOM: what a typed or pasted
// text may contain, how long it may be, when a run of typing closes one undo step, and how a list
// element (FAQ, documents) is written back into its prop. Pure, tested by test/inline-text.test.ts;
// the DOM side is #lib/editor/inline/textEditing.ts. Relative `.ts` imports: run by node in the tests.
import { resolveLink } from '../../blocks/parse.ts';
import { refusedInput, serializeList, setField, type ListValue } from '../traits/list.ts';

/** Longest prop value Sylius accepts (App\Yack\Content\Domain\PageBlocks::MAX_PROP_LENGTH), in characters. */
export const MAX_TEXT_LENGTH = 4000;

/** Pause after which a run of typing is one undo step (ms). */
export const TYPING_PAUSE = 1000;

/** Length as Sylius counts it (mb_strlen: code points, not UTF-16 units). */
export function textLength(text: string): number {
	let length = 0;
	for (const _ of text) length++;
	return length;
}

/** The first `max` characters (code points) of `text`. */
export function clampText(text: string, max: number): string {
	if (max <= 0) return '';
	let length = 0;
	let end = 0;
	for (const char of text) {
		if (length === max) return text.slice(0, end);
		length++;
		end += char.length;
	}
	return text;
}

/**
 * Plain text as it may be stored: line breaks as « \n », tabs as spaces, no other control character.
 * Without `multiline`, a line break becomes a space (the prop is one line).
 */
export function cleanText(text: string, multiline: boolean): string {
	const lines = text
		.replace(/\r\n?/g, '\n')
		.replace(/\t/g, ' ')
		// C0 and C1 controls except the line break, and the replacement character of a broken paste.
		.replace(/[\u0000-\u0009\u000B-\u001F\u007F-\u009F�]/g, '');
	return multiline ? lines : lines.replace(/\n/g, ' ');
}

/** Kind of change of an `InputEvent` (`inputType`): an undo step only holds changes of one kind. */
export type TypingKind = 'insert' | 'delete' | 'other';

export function typingKind(inputType: string): TypingKind {
	if (inputType.startsWith('insert')) return 'insert';
	if (inputType.startsWith('delete')) return 'delete';
	return 'other';
}

/**
 * True when the typed `data` ends a word (space, line break, punctuation): the undo step closes after it,
 * so Ctrl+Z takes back one word with its space, not one letter.
 */
export function endsWord(data: string | null): boolean {
	return data !== null && /[\s.,;:!?…)\]»"'’]$/u.test(data);
}

/** List read by the shop: FAQ (question, answer) or documents (label, link; refused links not shown). */
export type ListKind = 'faq' | 'links';

/**
 * Index in `list` of the element shown at `shown` by the shop (parse.ts: parseFaq skips an element whose
 * first column is empty, parseLinks also one whose link resolveLink refuses), -1 when there is none.
 */
export function shownItemIndex(list: ListValue, kind: ListKind, shown: number): number {
	let count = -1;
	for (const [index, item] of list.items.entries()) {
		const first = item.fields[0] ?? '';
		const last = item.fields[item.fields.length - 1] ?? '';
		if (first === '' || (kind === 'links' && resolveLink(last) === '')) continue;
		count++;
		if (count === shown) return index;
	}
	return -1;
}

/**
 * Text typed in a column of a list element (one line, no « | » added: the format of list.ts, same rule as
 * the panel's cards). Returns the text to keep: `next` when it is allowed, else `previous`.
 */
export function listFieldText(previous: string, next: string): string {
	return refusedInput(previous, next) === null ? next : previous;
}

/** Prop value of the list once the column `column` of its element `index` is `text`. */
export function listWithField(list: ListValue, index: number, column: number, text: string): string {
	return serializeList(setField(list, index, column, text));
}
