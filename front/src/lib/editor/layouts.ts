// Layout containers in the canvas of the builder (section, colonnes: #lib/blocks/containers.ts), with the
// markup of Section.svelte / Columns.svelte (#lib/blocks/layout.ts) so that the canvas renders the shop:
//  - a section and a colonnes are GrapesJS components whose view builds the element of the container
//    (classes and `--yl-*` of its style, kept up to date) and holds the children in it; a colonnes holds
//    one column component per column of its ratio, created and removed with the ratio (the blocks of a
//    removed column go to the last one);
//  - nesting rules (PageBlocks): a section takes colonnes and free content blocks, a column free content
//    blocks that are not full width (BlockType::FULL_WIDTH); containers stay at page level (colonnes in a
//    section too); bound blocks stay at page level or in their product zone (zones.ts);
//  - the blocks of a page become components and back recursively (load, save, revisions, sections).
import type { Component, ComponentDefinition, Editor } from 'grapesjs';
import { columnCount, columnsOf, isContainerType, isFullWidth, pickContainerStyle, type ContainerType } from '#lib/blocks/containers.js';
import { COLUMNS_LAYOUT, columnsRowClass, layoutMarkup, productColumn } from '#lib/blocks/layout.js';
import { blockDefinition, pickProps, REGISTRY, styleTraits } from '#lib/blocks/registry.js';
import type { PageBlock } from '#lib/blocks/types.js';
import { pickStyle, styleAttributes } from '#lib/editor/panels/blockStyle.js';
import { COLUMN_ATTRIBUTE, ZONE_COLUMN_TYPES, ZONE_TYPE, zoneBlocks } from './zones.js';

/** GrapesJS type of a column of a colonnes (made with it, follows its ratio; not in the palette). */
export const COLUMN_TYPE = 'yack-colonne';

/** Free block (pages and templates) that is not a container. */
const isFreeContent = (type: unknown): boolean => typeof type === 'string' && !isContainerType(type) && blockDefinition(type)?.scope === null;

/** A column takes free content blocks that are not full width. */
const fitsColumn = (type: unknown): boolean => isFreeContent(type) && !isFullWidth(type);

/** A section takes colonnes and free content blocks (full-width ones included). */
const fitsSection = (type: unknown): boolean => type === 'colonnes' || isFreeContent(type);

/**
 * Parent types a block may be dropped into: the page; a section and a column for a free content block
 * (not a column for a full-width one); a section for a colonnes; the column of its product zone for a
 * product block.
 */
export function blockParents(type: string): string[] {
	const zoneColumn = productColumn(type);
	if (zoneColumn !== null) return ['wrapper', ZONE_COLUMN_TYPES[zoneColumn]];
	if (type === 'colonnes') return ['wrapper', 'section'];
	if (!isFreeContent(type)) return ['wrapper'];
	return isFullWidth(type) ? ['wrapper', 'section'] : ['wrapper', 'section', COLUMN_TYPE];
}

/** A block of a page as stored or read (PageBlock, StoredBlock of the API): the shape the canvas needs. */
export interface BlockTree {
	type: string;
	props: Record<string, string>;
	style?: unknown;
	children?: readonly BlockTree[];
	columns?: readonly (readonly BlockTree[])[];
}

/** Content of a container as components (types unknown to the registry dropped). */
const known = (blocks: readonly BlockTree[] | undefined) => (blocks ?? []).filter((block) => blockDefinition(block.type) !== undefined);

/**
 * Component of a block, layout containers with their content (a colonnes: exactly the columns of its
 * ratio). `extra`: props given to every component, the columns included (read-only revision of the history).
 */
export function blockComponent(block: BlockTree, extra: ComponentDefinition = {}): ComponentDefinition {
	const definition: ComponentDefinition = { type: block.type, attributes: { ...block.props, ...styleAttributes(block.style) }, ...extra };
	if (block.type === 'section') definition.components = known(block.children).map((child) => blockComponent(child, extra));
	if (block.type === 'colonnes') {
		definition.components = columnsOf(block.columns, block.props.ratio).map((column) => ({
			type: COLUMN_TYPE,
			...extra,
			components: known(column).map((child) => blockComponent(child, extra))
		}));
	}
	return definition;
}

/** Content blocks of a component (a section: its children; a colonnes: none, its columns hold them). */
const blocksIn = (component: Component): PageBlock[] => component.components().models.flatMap((child) => componentBlock(child) ?? []);

/**
 * Block of a component as it is saved: props, the style when one is set (a container: its properties
 * only), the content of a container (children of a section, columns of a colonnes); null when the
 * component is not a Yack block.
 */
export function componentBlock(component: Component): PageBlock | null {
	const type: unknown = component.get('type');
	const definition = typeof type === 'string' ? blockDefinition(type) : undefined;
	if (!definition) return null;
	const attributes = component.getAttributes();
	const style = definition.container ? pickContainerStyle(pickStyle(attributes)) : pickStyle(attributes);
	const block: PageBlock = { type: definition.type, props: pickProps(definition.type, attributes), ...(Object.keys(style).length > 0 ? { style } : {}) };
	if (definition.type === 'section') block.children = blocksIn(component);
	if (definition.type === 'colonnes') {
		block.columns = component
			.components()
			.models.filter((child) => child.get('type') === COLUMN_TYPE)
			.map(blocksIn);
	}
	return block;
}

/** A component and, for a container, every block inside it, in document order. */
function withContent(component: Component): Component[] {
	if (!isContainerType(component.get('type'))) return [component];
	const inner = component.components().models.flatMap((child) => (child.get('type') === COLUMN_TYPE ? child.components().models : [child]));
	return [component, ...inner.flatMap(withContent)];
}

/**
 * Block components of the page in document order: containers before their content, the blocks of a
 * product zone in their place (side box, then main column).
 */
export function pageComponents(wrapper: Component): Component[] {
	return wrapper.components().models.flatMap((child) => (child.get('type') === ZONE_TYPE ? zoneBlocks(child) : withContent(child)));
}

/**
 * Columns of a colonnes as many as its ratio: missing ones added empty at the end; the blocks of the
 * extra ones moved (in order) to the end of the last column kept, then the extra columns removed.
 */
function syncColumns(colonnes: Component): void {
	const count = columnCount(colonnes.getAttributes().ratio);
	const columns = colonnes.components().models.filter((child) => child.get('type') === COLUMN_TYPE);
	if (columns.length < count) {
		colonnes.append(Array.from({ length: count - columns.length }, () => ({ type: COLUMN_TYPE })));
		return;
	}
	const last = columns[count - 1];
	for (const extra of columns.slice(count)) {
		for (const block of [...extra.components().models]) block.move(last, { at: last.components().length });
		extra.remove();
	}
}

type ContainerView = {
	el: HTMLElement;
	model: Component;
	listenTo: (target: Component, event: string, callback: () => void) => void;
};

/** Marker of the elements the view creates inside the element of the component. */
const LAYOUT_PART = 'yackLayout';

/**
 * Elements of a container inside the element of its component (created once): section = [container];
 * colonnes = [container, .container, row of the columns]. The last one holds the children.
 */
function parts(view: ContainerView, type: ContainerType): HTMLElement[] {
	const existing: HTMLElement[] = [];
	for (let el: HTMLElement | null = view.el.firstElementChild as HTMLElement | null; el && el.dataset[LAYOUT_PART] !== undefined; el = el.firstElementChild as HTMLElement | null) existing.push(el);
	if (existing.length > 0) return existing;
	const doc = view.el.ownerDocument;
	const created = (type === 'section' ? ['layout'] : ['layout', 'inner', 'row']).map((part) => {
		const el = doc.createElement('div');
		el.dataset[LAYOUT_PART] = part;
		return el;
	});
	// The canvas is an iframe: no `instanceof HTMLElement` across documents.
	created.reduce((parent, child) => (parent.append(child), child));
	if (type === 'section') created[0].setAttribute(COLUMN_ATTRIBUTE, 'section');
	else created[1].className = COLUMNS_LAYOUT.inner;
	view.el.prepend(created[0]);
	paint(view, type, created);
	return created;
}

/** Classes, `--yl-*` and anchor of the container, ratio / alignment / gap of the row (after every change of its attributes). */
function paint(view: ContainerView, type: ContainerType, elements: HTMLElement[]): void {
	const attributes = view.model.getAttributes();
	const markup = layoutMarkup(type, pickContainerStyle(pickStyle(attributes)), true);
	const [layout] = elements;
	layout.className = markup.class;
	if (markup.style) layout.setAttribute('style', markup.style);
	else layout.removeAttribute('style');
	if (markup.id) layout.id = markup.id;
	else layout.removeAttribute('id');
	if (type === 'colonnes') elements[2].className = columnsRowClass(pickProps('colonnes', attributes));
}

function containerView(type: ContainerType) {
	return {
		init(this: ContainerView) {
			this.listenTo(this.model, 'change:attributes', () => paint(this, type, parts(this, type)));
		},
		getChildrenContainer(this: ContainerView): HTMLElement {
			return parts(this, type).at(-1)!;
		}
	};
}

/**
 * Registers section, colonnes and column. `parents`: types a container may be dropped into
 * (blockParents); the traits of a container: its props, then the style fields it accepts.
 */
export function addLayoutTypes(editor: Editor): void {
	for (const entry of REGISTRY.filter((candidate) => candidate.container)) {
		const type = entry.type as ContainerType;
		const parents = new Set<unknown>(blockParents(type));
		editor.DomComponents.addType(type, {
			model: {
				defaults: {
					tagName: 'div',
					name: entry.label,
					attributes: { ...entry.defaults },
					traits: [...entry.traits, ...styleTraits(type)].map((trait) => ({ placeholder: '', ...trait, changeProp: false })),
					droppable: type === 'section' ? (source: Component) => fitsSection(source.get('type')) : false,
					editable: false,
					stylable: false,
					resizable: false,
					highlightable: true,
					layerable: false,
					draggable: (_source: Component, target: Component) => parents.has(target.get('type'))
				},
				...(type === 'colonnes'
					? {
							init(this: Component) {
								syncColumns(this);
								this.on('change:attributes:ratio', () => syncColumns(this));
							}
						}
					: {})
			},
			view: containerView(type)
		});
	}

	editor.DomComponents.addType(COLUMN_TYPE, {
		model: {
			defaults: {
				tagName: 'div',
				name: 'Colonne',
				classes: [COLUMNS_LAYOUT.column],
				attributes: { [COLUMN_ATTRIBUTE]: 'colonne' },
				removable: false,
				copyable: false,
				draggable: false,
				editable: false,
				stylable: false,
				resizable: false,
				layerable: true,
				traits: [],
				droppable: (source: Component) => fitsColumn(source.get('type'))
			},
			/** « Colonne 1 », « Colonne 2 »… (tree, badge of the canvas). */
			getName(this: Component) {
				return `Colonne ${this.index() + 1}`;
			}
		}
	});
}
