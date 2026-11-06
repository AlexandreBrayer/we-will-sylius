import { getContext, setContext } from 'svelte';
import type { Catalog } from './types.js';

export interface YackContext {
	/** Sylius URL as seen by the browser (assets, shop links). */
	syliusUrl: string;
	/** Products / taxons resolved server-side (public pages); null = components fetch /api/catalog. */
	catalog: Catalog | null;
	/** Rendering inside the builder canvas. */
	editor: boolean;
}

const KEY = Symbol('yack-cms');

export function yackContextMap(context: YackContext): Map<symbol, YackContext> {
	return new Map([[KEY, context]]);
}

export function setYackContext(context: YackContext): void {
	setContext(KEY, context);
}

export function getYackContext(): YackContext {
	return getContext<YackContext>(KEY) ?? { syliusUrl: '', catalog: null, editor: false };
}
