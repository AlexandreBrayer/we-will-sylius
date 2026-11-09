// Product zones of the template canvas: GrapesJS components with the markup of the product zone of
// TemplateView (#lib/blocks/layout.ts) — container, row, sticky side box and main column — whose
// columns only accept their blocks. The stored template stays a flat block list; zones are derived:
// built from the blocks on load (layoutRuns), kept as TemplateView would regroup the blocks after
// every change (a product block outside a zone joins the zone next to it or gets its own,
// consecutive zones merge, empty zones go), flattened back on save (flattenRuns). Not in the
// palette, neither removable nor duplicable by hand; a zone can be moved among the other blocks.
import type { Component, ComponentDefinition, Editor } from 'grapesjs';
import { flattenRuns, layoutRuns, PRODUCT_ZONE, productColumn, type ProductColumn, type Section } from '#lib/blocks/layout.js';
import type { PageBlock } from '#lib/blocks/types.js';

export const ZONE_TYPE = 'yack-zone-produit';
/** Column types of a product zone. */
export const ZONE_COLUMN_TYPES: Record<ProductColumn, string> = { side: 'yack-zone-encart', main: 'yack-zone-colonne' };
const COLUMNS: ProductColumn[] = ['side', 'main'];

/** Attribute of the element holding a column's blocks (canvas CSS: drop area of an empty column, product zone or colonnes). */
export const COLUMN_ATTRIBUTE = 'data-yack-column';

const classes = (value: string) => value.split(' ');

/** View rendering the children in an inner element (`className`): the row of a zone, the sticky box of the side column. */
function childrenIn(className: string, attributes: Record<string, string> = {}) {
	return {
		getChildrenContainer(this: { el: HTMLElement }): HTMLElement {
			// The canvas is an iframe: no `instanceof HTMLElement` across documents.
			const first = this.el.firstElementChild as HTMLElement | null;
			if (first && first.dataset.yackChildren !== undefined) return first;
			const inner = this.el.ownerDocument.createElement('div');
			inner.className = className;
			inner.dataset.yackChildren = '';
			for (const [name, value] of Object.entries(attributes)) inner.setAttribute(name, value);
			this.el.prepend(inner);
			return inner;
		}
	};
}

/** Registers the zone and column component types (no traits: nothing to set in the block panel). */
export function addZoneTypes(editor: Editor): void {
	const fixed = { removable: false, copyable: false, editable: false, stylable: false, resizable: false, layerable: true, traits: [] };
	editor.DomComponents.addType(ZONE_TYPE, {
		model: {
			defaults: {
				...fixed,
				tagName: 'div',
				name: 'Zone produit',
				classes: classes(PRODUCT_ZONE.zone),
				droppable: false,
				draggable: (_source: Component, target: Component) => target.get('type') === 'wrapper'
			}
		},
		view: childrenIn(PRODUCT_ZONE.row)
	});
	editor.DomComponents.addType(ZONE_COLUMN_TYPES.side, {
		model: {
			defaults: {
				...fixed,
				tagName: 'div',
				name: 'Encart latéral',
				classes: classes(PRODUCT_ZONE.side),
				draggable: false,
				droppable: (source: Component) => productColumn(source.get('type')) === 'side'
			}
		},
		view: childrenIn(PRODUCT_ZONE.sideBox, { [COLUMN_ATTRIBUTE]: 'side', 'data-test-product-box': '' })
	});
	editor.DomComponents.addType(ZONE_COLUMN_TYPES.main, {
		model: {
			defaults: {
				...fixed,
				tagName: 'div',
				name: 'Colonne principale',
				classes: classes(PRODUCT_ZONE.main),
				attributes: { [COLUMN_ATTRIBUTE]: 'main' },
				draggable: false,
				droppable: (source: Component) => productColumn(source.get('type')) === 'main'
			}
		}
	});
}

const zoneDefinition = (side: ComponentDefinition[] = [], main: ComponentDefinition[] = [], extra: ComponentDefinition = {}): ComponentDefinition => ({
	type: ZONE_TYPE,
	...extra,
	components: [
		{ type: ZONE_COLUMN_TYPES.side, ...extra, components: side },
		{ type: ZONE_COLUMN_TYPES.main, ...extra, components: main }
	]
});

/**
 * Page content of the canvas: the blocks, product blocks in their zones (as TemplateView lays them out).
 * `extra`: props given to the zones and their columns too (read-only revision of the history).
 */
export function zonedComponents<B extends { type: string }>(
	blocks: readonly B[],
	block: (block: B) => ComponentDefinition,
	extra: ComponentDefinition = {}
): ComponentDefinition[] {
	return layoutRuns(blocks).map((section) =>
		section.kind === 'block' ? block(section.block) : zoneDefinition(section.side.map(block), section.main.map(block), extra)
	);
}

function column(zone: Component, which: ProductColumn): Component {
	return zone.components().find((child: Component) => child.get('type') === ZONE_COLUMN_TYPES[which])!;
}

/** Blocks of a product zone in their place (side box, then main column: DOM order). */
export function zoneBlocks(zone: Component): Component[] {
	return COLUMNS.flatMap((which) => column(zone, which).components().models);
}

/** Flat block list of the canvas (zones flattened in their DOM order: `layoutRuns` gives the same zones back). */
export function collectBlocks(wrapper: Component, toBlock: (component: Component) => PageBlock | null): PageBlock[] {
	const blocksOf = (parent: Component): PageBlock[] =>
		parent.components().models.flatMap((component) => toBlock(component) ?? []);
	const sections: Section[] = [];
	wrapper.components().forEach((child: Component) => {
		if (child.get('type') === ZONE_TYPE) {
			sections.push({ kind: 'product', side: blocksOf(column(child, 'side')), main: blocksOf(column(child, 'main')) });
			return;
		}
		const block = toBlock(child);
		if (block) sections.push({ kind: 'block', block });
	});
	return flattenRuns(sections);
}

/**
 * Zones as TemplateView renders the page: a product block of the page joins the zone before it
 * (or gets a new one), consecutive zones merge, empty zones are removed. Idempotent.
 */
export function normalizeZones(wrapper: Component): void {
	let zone: Component | null = null;
	for (const child of [...wrapper.components().models]) {
		const type = child.get('type');
		if (type === ZONE_TYPE) {
			if (!zone) {
				zone = child;
				continue;
			}
			for (const which of COLUMNS) {
				for (const block of [...column(child, which).components().models]) block.move(column(zone, which));
			}
			child.remove();
			continue;
		}
		const which = productColumn(type);
		if (which === null) {
			zone = null;
			continue;
		}
		zone ??= wrapper.append(zoneDefinition(), { at: child.index() })[0];
		child.move(column(zone, which));
	}
	for (const child of [...wrapper.components().models]) {
		if (child.get('type') === ZONE_TYPE && COLUMNS.every((which) => column(child, which).components().length === 0)) child.remove();
	}
}

/**
 * Keeps the zones normalized after each change (drop, tree move, removal, undo / redo), in a
 * microtask: same call stack for the UndoManager, so one undo step covers the change and its zones.
 */
export function installZones(editor: Editor): () => void {
	let scheduled = false;
	let running = false;
	const schedule = () => {
		if (scheduled || running) return;
		scheduled = true;
		queueMicrotask(() => {
			scheduled = false;
			const wrapper = editor.getWrapper();
			if (!wrapper) return;
			running = true;
			try {
				normalizeZones(wrapper);
			} finally {
				running = false;
			}
		});
	};
	editor.on('component:add component:remove', schedule);
	return () => editor.off('component:add component:remove', schedule);
}
