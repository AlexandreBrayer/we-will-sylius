// Style of the block being rendered, given by BlockRenderer to its block component: the blocks read in it
// what changes their own markup (tag of the main title, vertical alignment of a two-column row: style.ts
// titleTag(), rowAlignment()); the rest of the style stays on the wrapper (styleToCss). A getter, so that a
// block follows a style changed after its mount.
import { getContext, setContext } from 'svelte';

const KEY = Symbol('yack-cms-block-style');

/** BlockRenderer: the style of its block, as stored (read through readStyle() by the helpers of style.ts). */
export function setBlockStyle(read: () => unknown): void {
	setContext(KEY, read);
}

/** Block component: the style of the block it renders (`undefined` outside BlockRenderer: no style). */
export function blockStyle(): () => unknown {
	return getContext<(() => unknown) | undefined>(KEY) ?? (() => undefined);
}
