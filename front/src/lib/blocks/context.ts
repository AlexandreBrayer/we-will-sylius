import { getContext, setContext } from 'svelte';
import { VISITOR, type Viewer } from '#lib/types.js';
import type { Catalog } from './types.js';

export interface YackContext {
	/** Who is browsing: product prices / notices of the "produits" block. */
	viewer: Viewer;
	/** Products / taxons resolved server-side (public pages); null = components fetch /cms-admin/api/catalog. */
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
	return getContext<YackContext>(KEY) ?? { viewer: VISITOR, catalog: null, editor: false };
}
