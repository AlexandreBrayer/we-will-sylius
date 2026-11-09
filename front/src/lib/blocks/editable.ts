// In-place editing of the texts of a block (D8). A block puts `{@attach edit('title')}` on the element
// that shows the prop as plain text (`<h2 {@attach edit('title')}>{title}</h2>`):
//  - shop: `edit()` gives nothing, the element renders exactly as before (an attachment is never part of
//    the server rendering: no attribute, no comment);
//  - builder canvas: the element becomes editable in place (double click or Entrée), through the text
//    editing the canvas gives to the block (#lib/editor/inline/textEditing.ts).
// An attachment on the existing element rather than a wrapping component: a component around the text
// would add hydration comments to the shop's HTML.
import type { Attachment } from 'svelte/attachments';
import { getTextEditing, type TextTarget } from './context.js';

/** Attachment of a text prop: `edit('title')`, or a column of a list element: `edit('items', { kind: 'faq', item: 0, column: 1 })`. */
export type EditText = (prop: string, list?: TextTarget['list']) => Attachment<HTMLElement> | undefined;

/** Reads the canvas text editing of the block; call it in the component's script. */
export function textEditor(): EditText {
	const editing = getTextEditing();
	if (!editing) return () => undefined;
	return (prop, list) => (el) => editing.attach(el, list ? { prop, list } : { prop });
}
