// In-place editing of the texts of a block in the builder canvas (D8). The canvas gives one controller
// per mounted block (grapesSetup.ts svelteView); each text element of the block attaches to it
// (#lib/blocks/editable.ts). Behaviour:
//  - idle: the element is focusable (Tab), marked `data-yack-edit`, described by its `title`; a double
//    click or Entrée (F2 too) starts the editing. A single click does not take the focus, so the GrapesJS
//    shortcuts (Ctrl+Z, Suppr) keep working on the selected block;
//  - editing: `contenteditable="plaintext-only"` (`true` where the browser does not know it: paste, drop,
//    line breaks and formatting are then handled here all the same), `role="textbox"` named by the
//    field's label. Each change goes into the model with `component.addAttributes()`: the panel, the
//    project data and the save follow. The block is not mounted again while typing (the caret would
//    jump); a change of the model made elsewhere meanwhile is applied to the text (undo of the prop) or
//    ends the editing (another setting);
//  - undo: the typing writes `partial` updates, which GrapesJS's UndoManager does not record but whose
//    starting point it keeps; the step is recorded when a word ends (space, punctuation, line break),
//    after a pause of TYPING_PAUSE, when the kind of change switches (typing, erasing), on a paste and when
//    the editing ends. Ctrl+Z takes back one word or one pause, not one letter;
//  - end: Échap, Entrée on a one-line text, or a click elsewhere; the block is mounted again when its
//    markup no longer matches the model (text emptied, line break placeholder…).
// Only plain text: no HTML in a prop. Line breaks only for a multiline prop (trait `textarea`); the
// length is bounded like Sylius (MAX_TEXT_LENGTH); a list column (FAQ, documents) takes no « | » nor
// line break, as in the panel's cards.
import type { Component as GjsComponent, Editor } from 'grapesjs';
import { flushSync } from 'svelte';
import type { BlockTextEditing, TextTarget } from '#lib/blocks/context.js';
import { blockDefinition, LIST_TRAITS } from '#lib/blocks/registry.js';
import type { BlockType } from '#lib/blocks/types.js';
import { parseList, type ListValue } from '#lib/editor/traits/list.js';
import {
	MAX_TEXT_LENGTH,
	TYPING_PAUSE,
	clampText,
	cleanText,
	endsWord,
	listFieldText,
	listWithField,
	shownItemIndex,
	textLength,
	typingKind,
	type TypingKind
} from './text.js';

/** What the canvas gives for one mounted block. */
export interface TextEditingHost {
	editor: Editor;
	model: GjsComponent;
	type: BlockType;
	/** Element of the GrapesJS component: the block is mounted inside. */
	host: HTMLElement;
	/** Mounts the block again from the model. */
	rerender(): void;
}

export interface TextEditingController extends BlockTextEditing {
	/** The model's attributes changed: true when handled without mounting the block again (typing). */
	handleChange(): boolean;
	/** The block's view is removed. */
	dispose(): void;
}

/** Field of an editable element: its key in the block, label, and whether it takes line breaks. */
interface Field {
	key: string;
	label: string;
	multiline: boolean;
	/** Number of columns of the list (list target only). */
	columns: number;
}

const IDLE_HINT = 'double-cliquez ou appuyez sur Entrée pour modifier le texte';
const EDITING_HINT = 'Échap ou un clic ailleurs pour terminer';

/** One editing at a time in the canvas. */
let active: Session | null = null;

function fieldOf(type: BlockType, target: TextTarget): Field | null {
	const trait = blockDefinition(type)?.traits.find((candidate) => candidate.name === target.prop);
	if (!trait) return null;
	if (!target.list) return { key: target.prop, label: trait.label, multiline: trait.type === 'textarea', columns: 0 };
	const list = LIST_TRAITS[type]?.[target.prop];
	const column = list?.columns?.[target.list.column];
	if (!list?.columns || !column) return null;
	return {
		key: `${target.prop}:${target.list.item}:${target.list.column}`,
		label: `${list.itemLabel ?? 'Élément'} ${target.list.item + 1} : ${column.label}`,
		multiline: false,
		columns: list.columns.length
	};
}

const isModifier = (event: KeyboardEvent): boolean => event.ctrlKey || event.metaKey;
const isUndo = (event: KeyboardEvent): boolean => isModifier(event) && !event.shiftKey && event.key.toLowerCase() === 'z';
const isRedo = (event: KeyboardEvent): boolean =>
	isModifier(event) && ((event.shiftKey && event.key.toLowerCase() === 'z') || (!event.shiftKey && event.key.toLowerCase() === 'y'));

/**
 * Text shown by the element: text nodes, a `<br>` as a line break (a last `<br>` is the browser's
 * placeholder of an empty line), a block element (`contenteditable="true"` in some browsers) starts a line.
 */
function textOf(el: HTMLElement): string {
	let text = '';
	const walk = (node: Node): void => {
		node.childNodes.forEach((child, index) => {
			if (child.nodeType === Node.TEXT_NODE) {
				text += (child as Text).data;
			} else if (child.nodeName === 'BR') {
				if (!(node === el && index === node.childNodes.length - 1)) text += '\n';
			} else if (child.nodeType === Node.ELEMENT_NODE && child.nodeName.toLowerCase() !== 'svg') {
				if (/^(DIV|P)$/.test(child.nodeName) && text !== '' && !text.endsWith('\n')) text += '\n';
				walk(child);
			}
		});
	};
	walk(el);
	return text;
}

/** Caret offset in the text of `el` (UTF-16 units), the end when the selection is elsewhere. */
function caretOffset(el: HTMLElement): number {
	const selection = el.ownerDocument.getSelection();
	if (!selection || selection.rangeCount === 0) return textOf(el).length;
	const range = selection.getRangeAt(0);
	if (!el.contains(range.startContainer)) return textOf(el).length;
	const before = el.ownerDocument.createRange();
	before.selectNodeContents(el);
	before.setEnd(range.startContainer, range.startOffset);
	return before.toString().length;
}

/** Puts the caret at `offset` in the text of `el` (null: at the end, before a line break placeholder). */
function placeCaret(el: HTMLElement, offset: number | null): void {
	const doc = el.ownerDocument;
	const range = doc.createRange();
	let placed = false;
	if (offset !== null) {
		const walker = doc.createTreeWalker(el, NodeFilter.SHOW_TEXT);
		let left = offset;
		for (let node = walker.nextNode() as Text | null; node; node = walker.nextNode() as Text | null) {
			if (left <= node.data.length) {
				range.setStart(node, left);
				placed = true;
				break;
			}
			left -= node.data.length;
		}
	}
	if (!placed) {
		const last = el.lastChild;
		if (last?.nodeName === 'BR') range.setStartBefore(last);
		else {
			range.selectNodeContents(el);
			range.collapse(false);
		}
	}
	range.collapse(true);
	const selection = doc.getSelection();
	selection?.removeAllRanges();
	selection?.addRange(range);
}

/** Caret under the pointer (double click), null when the browser cannot tell. */
function rangeAtPoint(doc: Document, x: number, y: number): Range | null {
	const withPosition = doc as Document & { caretPositionFromPoint?: (x: number, y: number) => { offsetNode: Node; offset: number } | null };
	const position = withPosition.caretPositionFromPoint?.(x, y);
	if (position) {
		const range = doc.createRange();
		range.setStart(position.offsetNode, position.offset);
		return range;
	}
	const withRange = doc as Document & { caretRangeFromPoint?: (x: number, y: number) => Range | null };
	return withRange.caretRangeFromPoint?.(x, y) ?? null;
}

/** Opens or closes the answer of a FAQ question (`<summary>`); false for another element. */
function toggleAnswer(el: HTMLElement): boolean {
	const details = el.parentElement;
	if (el.nodeName !== 'SUMMARY' || details?.nodeName !== 'DETAILS') return false;
	(details as HTMLDetailsElement).open = !(details as HTMLDetailsElement).open;
	return true;
}

/** Plain text editing of one element. */
class Session {
	/** Model attributes at the start (or the last change of the prop): another setting changing ends the editing. */
	private snapshot: Record<string, unknown>;
	/** Prop value last written by the typing (or read after a change of the prop elsewhere). */
	private written: string;
	/** Text of the element. */
	private text: string;
	/** List prop: the list as read at the start (an element emptied while typing keeps its place). */
	private list: ListValue | null = null;
	private item = -1;
	/** Open undo step: kind of its changes, partial updates not recorded yet, prop value at its start. */
	private group: { kind: TypingKind | null; partial: boolean; start: string } = { kind: null, partial: false, start: '' };
	private timer: ReturnType<typeof setTimeout> | undefined;
	/** Caret before the change being typed (put back when the change is refused). */
	private caret = 0;
	private readonly listeners: [string, (event: Event) => void][];

	constructor(
		readonly controller: Controller,
		readonly el: HTMLElement,
		readonly target: TextTarget,
		private readonly field: Field
	) {
		const attributes = controller.host.model.getAttributes();
		this.snapshot = { ...attributes };
		this.written = String(attributes[target.prop] ?? '');
		this.text = this.textFor(this.written) ?? '';
		this.listeners = [
			['beforeinput', (event) => this.beforeInput(event as InputEvent)],
			['input', (event) => this.input(event as InputEvent)],
			['paste', (event) => this.paste(event as ClipboardEvent)],
			['drop', (event) => this.drop(event as DragEvent)],
			['dragstart', (event) => event.preventDefault()],
			['blur', () => active === this && this.finish(false)]
		];
	}

	/** Text of the element for a prop value; null when the list element is no longer shown. */
	private textFor(value: string): string | null {
		const { list: target } = this.target;
		if (!target) return value;
		this.list = parseList(value, this.field.columns);
		this.item = shownItemIndex(this.list, target.kind, target.item);
		return this.item < 0 ? null : (this.list.items[this.item]?.fields[target.column] ?? null);
	}

	/** Prop value for a text of the element. */
	private valueFor(text: string): string {
		const { list: target } = this.target;
		return target && this.list ? listWithField(this.list, this.item, target.column, text) : text;
	}

	/** Starts editing; false when the element no longer shows its target. */
	start(point: { x: number; y: number } | null): boolean {
		if (this.target.list && this.item < 0) return false;
		const { el } = this;
		try {
			el.contentEditable = 'plaintext-only';
		} catch {
			// Browsers without `plaintext-only` (Firefox before 136): paste, drop, line breaks and
			// formatting are handled by this class.
			el.contentEditable = 'true';
		}
		el.setAttribute('role', 'textbox');
		el.setAttribute('aria-multiline', String(this.field.multiline));
		el.setAttribute('aria-label', this.field.label);
		el.title = `${this.field.label} : ${EDITING_HINT}`;
		el.spellcheck = true;
		for (const [type, listener] of this.listeners) el.addEventListener(type, listener);
		active = this;
		el.focus({ preventScroll: true });
		const range = point ? rangeAtPoint(el.ownerDocument, point.x, point.y) : null;
		if (range && el.contains(range.startContainer)) {
			const selection = el.ownerDocument.getSelection();
			selection?.removeAllRanges();
			selection?.addRange(range);
		} else {
			placeCaret(el, null);
		}
		return true;
	}

	keydown(event: KeyboardEvent): void {
		// Nothing typed here reaches the GrapesJS shortcuts (Suppr would remove the block).
		event.stopPropagation();
		if (event.isComposing) return;
		if (event.key === 'Escape') {
			event.preventDefault();
			this.finish(true);
		} else if (event.key === 'Enter') {
			event.preventDefault();
			if (this.field.multiline && !isModifier(event)) this.insert('\n', false);
			else this.finish(true);
		} else if (isUndo(event)) {
			event.preventDefault();
			this.history('undo');
		} else if (isRedo(event)) {
			event.preventDefault();
			this.history('redo');
		} else if (event.key === ' ' && !isModifier(event) && this.el.closest('summary')) {
			// A FAQ question (`<summary>`) takes the space as « open the answer »: typed here instead.
			event.preventDefault();
			this.insert(' ', false);
		} else if (isModifier(event) && ['b', 'i', 'u'].includes(event.key.toLowerCase())) {
			// Formatting shortcuts of `contenteditable="true"`: plain text only.
			event.preventDefault();
		}
	}

	private beforeInput(event: InputEvent): void {
		const type = event.inputType;
		if (type.startsWith('format') || type === 'insertLink') {
			event.preventDefault();
		} else if (type === 'historyUndo' || type === 'historyRedo') {
			event.preventDefault();
			this.history(type === 'historyUndo' ? 'undo' : 'redo');
		} else if (type === 'insertParagraph' || type === 'insertLineBreak') {
			event.preventDefault();
			if (this.field.multiline) this.insert('\n', false);
		} else if (type.startsWith('insertFrom')) {
			// Paste or drop that the paste / drop events did not catch: as plain text.
			event.preventDefault();
			this.insert(event.dataTransfer?.getData('text/plain') ?? event.data ?? '', true);
		} else if ((type === 'insertText' || type === 'insertReplacementText') && event.data !== null && (/[\r\n\t]/.test(event.data) || (this.target.list && event.data.includes('|')))) {
			// A line break or a tab typed (or given by the spell checker), a « | » in a list column: cleaned.
			event.preventDefault();
			this.insert(event.data, false);
		} else {
			this.caret = caretOffset(this.el);
		}
	}

	private input(event: InputEvent): void {
		const read = cleanText(textOf(this.el), this.field.multiline);
		const text = this.target.list ? listFieldText(this.text, read) : read;
		if (text !== read || textLength(this.valueFor(text)) > MAX_TEXT_LENGTH) {
			// Refused (too long, a « | » in a list…): the element goes back to the text before the change.
			this.show(this.text, this.caret);
			return;
		}
		this.apply(text, typingKind(event.inputType), event.data, false);
	}

	private paste(event: ClipboardEvent): void {
		event.preventDefault();
		event.stopPropagation();
		this.insert(event.clipboardData?.getData('text/plain') ?? '', true);
	}

	private drop(event: DragEvent): void {
		// No drop into a text being edited (nor of a block of the palette).
		event.preventDefault();
		event.stopPropagation();
	}

	/** Inserts plain text at the caret (paste, line break), within the length allowed. */
	private insert(raw: string, paste: boolean): void {
		const { el } = this;
		const selection = el.ownerDocument.getSelection();
		if (!selection || selection.rangeCount === 0) return;
		const range = selection.getRangeAt(0);
		if (!el.contains(range.commonAncestorContainer)) return;
		let data = cleanText(raw, this.field.multiline);
		if (this.target.list) data = data.replace(/\|/g, '');
		const room = MAX_TEXT_LENGTH - textLength(this.valueFor(this.text)) + textLength(range.toString());
		data = clampText(data, room);
		if (data === '' && range.collapsed) return;
		range.deleteContents();
		if (data !== '') {
			const node = el.ownerDocument.createTextNode(data);
			range.insertNode(node);
			range.setStartAfter(node);
		}
		range.collapse(true);
		selection.removeAllRanges();
		selection.addRange(range);
		el.normalize();
		const text = cleanText(textOf(el), this.field.multiline);
		this.lineBreakPlaceholder(text);
		if (paste) this.closeGroup();
		this.apply(text, 'insert', data, paste);
	}

	/** A text ending with a line break needs a last `<br>` for the caret to reach the new line. */
	private lineBreakPlaceholder(text: string): void {
		if (text.endsWith('\n') && this.el.lastChild?.nodeName !== 'BR') this.el.appendChild(this.el.ownerDocument.createElement('br'));
	}

	/** Shows `text` in the element, caret at `caret` (null: at the end). */
	private show(text: string, caret: number | null): void {
		this.el.textContent = text;
		this.lineBreakPlaceholder(text);
		placeCaret(this.el, caret === null ? null : Math.min(caret, text.length));
	}

	/** Writes a change of the text into the model (see the undo rules at the top of the file). */
	private apply(text: string, kind: TypingKind, data: string | null, final: boolean): void {
		if (text === this.text) return;
		if (this.group.kind !== null && this.group.kind !== kind) this.closeGroup();
		if (this.group.kind === null) this.group = { kind, partial: false, start: this.written };
		this.text = text;
		const value = this.valueFor(text);
		const { model } = this.controller.host;
		this.written = value;
		if (final || kind === 'other' || (kind === 'insert' && endsWord(data))) {
			model.addAttributes({ [this.target.prop]: value });
			this.group = { kind: null, partial: false, start: value };
			clearTimeout(this.timer);
			return;
		}
		model.addAttributes({ [this.target.prop]: value }, { partial: true });
		this.group.partial = true;
		clearTimeout(this.timer);
		this.timer = setTimeout(() => this.closeGroup(), TYPING_PAUSE);
	}

	/**
	 * Records the open undo step: GrapesJS's UndoManager adds one step from the value before the first
	 * partial update to the current one at the next complete change of the component.
	 */
	private closeGroup(): void {
		clearTimeout(this.timer);
		const { partial, start } = this.group;
		this.group = { kind: null, partial: false, start: this.written };
		if (!partial || start === this.written) return;
		const { model } = this.controller.host;
		model.trigger('change', model, {});
	}

	private history(which: 'undo' | 'redo'): void {
		this.closeGroup();
		const um = this.controller.host.editor.UndoManager;
		if (which === 'undo') um.undo();
		else um.redo();
	}

	/**
	 * The model changed while editing: true when the change is the typing itself, or a change of the prop
	 * made elsewhere (undo, panel) now shown in the element; false when the editing had to end.
	 */
	modelChanged(): boolean {
		const attributes = this.controller.host.model.getAttributes();
		const { prop } = this.target;
		// Props and style of the block; GrapesJS's own attributes (an `id` given on the way) do not count.
		const defaults = blockDefinition(this.controller.host.type)?.defaults ?? {};
		const keys = new Set([...Object.keys(this.snapshot), ...Object.keys(attributes)].filter((key) => key in defaults || key.startsWith('yb-')));
		keys.delete(prop);
		for (const key of keys) {
			if (this.snapshot[key] !== attributes[key]) return this.abort();
		}
		const value = String(attributes[prop] ?? '');
		if (value === this.written) return true;
		const text = this.textFor(value);
		if (text === null) return this.abort();
		clearTimeout(this.timer);
		this.group = { kind: null, partial: false, start: value };
		this.snapshot = { ...attributes };
		this.written = value;
		this.text = text;
		this.show(text, null);
		return true;
	}

	/** Leaves the editing state of the element (listeners, attributes). */
	private leave(): void {
		const { el } = this;
		if (active === this) active = null;
		clearTimeout(this.timer);
		for (const [type, listener] of this.listeners) el.removeEventListener(type, listener);
		el.removeAttribute('contenteditable');
		el.removeAttribute('role');
		el.removeAttribute('aria-multiline');
		el.removeAttribute('aria-label');
		el.title = `${this.field.label} : ${IDLE_HINT}`;
	}

	/** Ends the editing because the block is mounted again or removed. */
	abort(): false {
		this.closeGroup();
		this.leave();
		return false;
	}

	/** Ends the editing (Échap, Entrée, click elsewhere); `refocus`: the focus stays on the text. */
	finish(refocus: boolean): void {
		this.closeGroup();
		this.leave();
		const { el, text } = this;
		const matches = text.trim() !== '' && el.childNodes.length === 1 && el.firstChild?.nodeType === Node.TEXT_NODE && el.textContent === text;
		if (matches) {
			if (refocus) el.focus({ preventScroll: true });
			return;
		}
		// The markup no longer matches the model (emptied text, line break placeholder): mounted again.
		this.controller.host.rerender();
		if (!refocus) return;
		flushSync();
		this.controller.find(this.field.key)?.focus({ preventScroll: true });
	}
}

class Controller implements TextEditingController {
	constructor(readonly host: TextEditingHost) {}

	find(key: string): HTMLElement | null {
		return this.host.host.querySelector<HTMLElement>(`[data-yack-edit="${CSS.escape(key)}"]`);
	}

	/**
	 * A component that cannot be selected is read-only (revision shown by the history, D11): its texts are
	 * not editable in place either.
	 */
	private editable(): boolean {
		return this.host.model.get('selectable') !== false;
	}

	attach(el: HTMLElement, target: TextTarget): () => void {
		const field = fieldOf(this.host.type, target);
		if (!field || !this.editable()) return () => {};
		const { editor, model } = this.host;
		el.dataset.yackEdit = field.key;
		el.tabIndex = 0;
		el.title = `${field.label} : ${IDLE_HINT}`;
		const editingHere = (): Session | null => (active?.el === el ? active : null);
		const begin = (point: { x: number; y: number } | null): void => {
			if (!this.editable()) return;
			active?.finish(false);
			// Ending another editing of this block may have mounted it again.
			const current = el.isConnected ? el : this.find(field.key);
			if (!current) return;
			if (current !== el) {
				current.dispatchEvent(new CustomEvent('yack-edit-start', { detail: point }));
				return;
			}
			if (editor.getSelected() !== model) editor.select(model);
			new Session(this, el, target, field).start(point);
		};
		const listeners: [string, (event: Event) => void][] = [
			[
				'keydown',
				(event) => {
					const keyboard = event as KeyboardEvent;
					const session = editingHere();
					if (session) return session.keydown(keyboard);
					if (keyboard.key === ' ' && toggleAnswer(el)) {
						keyboard.preventDefault();
					} else if ((keyboard.key === 'Enter' || keyboard.key === 'F2') && !isModifier(keyboard) && !keyboard.altKey) {
						keyboard.preventDefault();
						keyboard.stopPropagation();
						begin(null);
					} else if (isUndo(keyboard) || isRedo(keyboard)) {
						// A focused text hides the GrapesJS shortcuts: undo / redo stay available.
						keyboard.preventDefault();
						keyboard.stopPropagation();
						if (isUndo(keyboard)) editor.UndoManager.undo();
						else editor.UndoManager.redo();
						// The block may have been mounted again: the focus goes back to the same text.
						if (!el.isConnected) {
							flushSync();
							this.find(field.key)?.focus({ preventScroll: true });
						}
					}
				}
			],
			['keyup', (event) => editingHere() && event.stopPropagation()],
			['keypress', (event) => editingHere() && event.stopPropagation()],
			[
				'mousedown',
				(event) => {
					const mouse = event as MouseEvent;
					if (editingHere() || mouse.button !== 0) return;
					// A click on another text ends the editing; it does not take the focus (GrapesJS shortcuts).
					active?.finish(false);
					mouse.preventDefault();
				}
			],
			[
				'click',
				(event) => {
					// No link followed from the canvas.
					event.preventDefault();
					// GrapesJS keeps the clicks of the canvas: a FAQ question opens its answer here, as on the
					// shop (a double click opens and closes it, then edits the question).
					if (!editingHere()) toggleAnswer(el);
				}
			],
			[
				'dblclick',
				(event) => {
					if (editingHere()) return;
					const mouse = event as MouseEvent;
					mouse.preventDefault();
					begin({ x: mouse.clientX, y: mouse.clientY });
				}
			],
			['yack-edit-start', (event) => begin((event as CustomEvent<{ x: number; y: number } | null>).detail)],
			['focus', () => this.editable() && editor.getSelected() !== model && editor.select(model)]
		];
		for (const [type, listener] of listeners) el.addEventListener(type, listener);
		return () => {
			editingHere()?.abort();
			for (const [type, listener] of listeners) el.removeEventListener(type, listener);
		};
	}

	handleChange(): boolean {
		return active !== null && active.controller === this ? active.modelChanged() : false;
	}

	dispose(): void {
		if (active?.controller === this) active.abort();
	}
}

/** In-place text editing of a mounted block. */
export function createTextEditing(host: TextEditingHost): TextEditingController {
	return new Controller(host);
}
