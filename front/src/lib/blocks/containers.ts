// Layout containers of the CMS (App\Yack\Content\Domain\BlockType, PageBlocks): a page stays an ordered
// list of nodes, where a `section` holds `children` (colonnes or free content blocks) and a `colonnes`
// holds `columns` (one list of content blocks per column of its ratio). Sylius validates the tree; this
// pure module (no Svelte, no GrapesJS, run by node in the tests) gives the shop and the builder the same
// constants, the walk of the tree and the French path of a node (« Section 1 › colonnes 2 › colonne 1 › bloc 1 »).
import { readStyle, type BlockStyle, type StyleProperty } from './style.ts';

/** Container types (BlockType::SECTION, BlockType::COLUMNS). */
export const CONTAINER_TYPES = ['section', 'colonnes'] as const;

export type ContainerType = (typeof CONTAINER_TYPES)[number];

export const isContainerType = (type: unknown): type is ContainerType => type === 'section' || type === 'colonnes';

/** BlockType::COLUMN_RATIOS (first = default) and their number of columns. */
export const COLUMN_RATIOS = ['50-50', '33-66', '66-33', '33-33-33'] as const;

export type ColumnRatio = (typeof COLUMN_RATIOS)[number];

/** BlockType::COLUMN_ALIGNMENTS (vertical alignment of the columns, first = default). */
export const COLUMN_ALIGNMENTS = ['haut', 'centre', 'bas'] as const;

/** BlockType::COLUMN_GAPS: charte spacing keys between the columns (first = default). */
export const COLUMN_GAPS = ['m', '0', 's', 'l', 'xl'] as const;

/** BlockType::FULL_WIDTH: blocks refused inside a column (they span the page). */
export const FULL_WIDTH_TYPES = ['hero', 'produits', 'categories', 'cta', 'chiffres-cles', 'tuiles'] as const;

export const isFullWidth = (type: unknown): boolean => (FULL_WIDTH_TYPES as readonly unknown[]).includes(type);

/**
 * Why a column refuses a block (message of the cancelled drop in the builder): a container, a full-width
 * block, otherwise a block bound to the record. `label`: name of the block for the author.
 */
export function columnRefusal(type: unknown, label: string): string {
	if (type === 'section') return `Dépôt annulé : « ${label} » se place à la racine de la page, jamais dans une colonne.`;
	if (type === 'colonnes') return `Dépôt annulé : « ${label} » se placent dans la page ou dans une section, jamais dans une colonne.`;
	if (isFullWidth(type)) return `Dépôt annulé : « ${label} » est un bloc pleine largeur, il se place dans une section ou dans la page, pas dans une colonne.`;
	return `Dépôt annulé : « ${label} » est lié à la fiche, il reste au niveau de la page, pas dans une colonne.`;
}

/** BlockType::CONTAINER_STYLE: style properties a container accepts, in the order of BlockStyle::PROPERTIES. */
export const CONTAINER_STYLE = ['fond', 'texte', 'hautEspace', 'basEspace', 'largeur', 'masquerMobile', 'masquerOrdinateur', 'ancre'] as const satisfies readonly StyleProperty[];

export const isContainerStyle = (property: StyleProperty): boolean => (CONTAINER_STYLE as readonly string[]).includes(property);

/** The properties of a style a container keeps (BlockType::CONTAINER_STYLE); Sylius refuses the others on a container. */
export function pickContainerStyle(style: BlockStyle): BlockStyle {
	const kept: BlockStyle = {};
	for (const property of CONTAINER_STYLE) if (style[property] !== undefined) kept[property] = style[property];
	return kept;
}

/** Known ratio of a prop value, the default one otherwise. */
export function columnRatio(value: unknown): ColumnRatio {
	return COLUMN_RATIOS.find((ratio) => ratio === value) ?? COLUMN_RATIOS[0];
}

/** Number of columns of a ratio (unknown ratio: the default one, 2). */
export function columnCount(ratio: unknown): number {
	return columnRatio(ratio).split('-').length;
}

/** A node of a block tree, as stored or read (only the shape matters here; no type: a content block). */
export interface TreeBlock {
	type?: string;
	style?: unknown;
	children?: readonly TreeBlock[];
	columns?: readonly (readonly TreeBlock[])[];
}

/** Direct content of a node: the children of a section, the blocks of every column of a colonnes, in order. */
export function innerBlocks<B extends TreeBlock>(node: B): B[] {
	return [...((node.children ?? []) as B[]), ...((node.columns ?? []) as (readonly B[])[]).flat()] as B[];
}

/** Every node of the tree, depth first, a container before its content (document order). */
export function flattenNodes<B extends TreeBlock>(blocks: readonly B[]): B[] {
	return blocks.flatMap((block) => [block, ...flattenNodes(innerBlocks(block))]);
}

/** Number of nodes of the tree, containers included (PageBlocks::count(), limited by MAX_BLOCKS). */
export const countNodes = (blocks: readonly TreeBlock[]): number => flattenNodes(blocks).length;

/** A node with its place in the tree. */
export interface NodePath<B extends TreeBlock = TreeBlock> {
	node: B;
	/** Position of its root node in the page, from 1. */
	root: number;
	/**
	 * French path, as Sylius writes it (StyleDeviation::$where): « Bloc n°2 », « Section 1 », « Colonnes 1 »
	 * at the root; « Section 2 › bloc 3 », « Section 2 › colonnes 1 › colonne 2 › bloc 1 » below (position
	 * among all the children of its parent, from 1).
	 */
	where: string;
}

/** Every node with its path, depth first, a container before its content. */
export function nodePaths<B extends TreeBlock>(blocks: readonly B[]): NodePath<B>[] {
	const out: NodePath<B>[] = [];
	const visit = (node: B, root: number, where: string) => {
		out.push({ node, root, where });
		// A section holds colonnes and content blocks, a column content blocks only (PageBlocks).
		(node.children ?? []).forEach((child, index) => visit(child as B, root, `${where} › ${child.type === 'colonnes' ? 'colonnes' : 'bloc'} ${index + 1}`));
		(node.columns ?? []).forEach((column, columnIndex) =>
			column.forEach((child, index) => visit(child as B, root, `${where} › colonne ${columnIndex + 1} › bloc ${index + 1}`))
		);
	};
	blocks.forEach((block, index) => {
		const position = index + 1;
		visit(block, position, block.type === 'section' ? `Section ${position}` : block.type === 'colonnes' ? `Colonnes ${position}` : `Bloc n°${position}`);
	});
	return out;
}

/** The columns of a colonnes node, exactly as many as its ratio: missing ones empty, extra blocks in the last one. */
export function columnsOf<B>(columns: readonly (readonly B[])[] | undefined, ratio: unknown): B[][] {
	const count = columnCount(ratio);
	const list = (columns ?? []).map((column) => [...column]);
	const out = Array.from({ length: count }, (_, index) => list[index] ?? []);
	out[count - 1] = [...out[count - 1], ...list.slice(count).flat()];
	return out;
}

/** Block read from the API (pages, templates, revisions, sections): props `[]` and style `[]` of PHP read as `{}`. */
export interface StoredBlock {
	type: string;
	props: Record<string, string>;
	style: BlockStyle;
	/** Section only (always set on a section). */
	children?: StoredBlock[];
	/** Colonnes only (always set on a colonnes). */
	columns?: StoredBlock[][];
}

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);

/** Blocks of the API (a list; anything else gives none), unreadable nodes dropped. */
export const readBlocks = (value: unknown): StoredBlock[] =>
	Array.isArray(value)
		? value.flatMap((raw: unknown) => {
				const read = readBlock(raw);
				return read ? [read] : [];
			})
		: [];

/** One node of the API with its content, recursively; null when it is not a block (no type). */
export function readBlock(raw: unknown): StoredBlock | null {
	if (!isRecord(raw) || typeof raw.type !== 'string' || raw.type === '') return null;
	const props: Record<string, string> = {};
	if (isRecord(raw.props)) {
		for (const [name, value] of Object.entries(raw.props)) if (typeof value === 'string') props[name] = value;
	}
	const block: StoredBlock = { type: raw.type, props, style: readStyle(raw.style) };
	if (raw.type === 'section') block.children = readBlocks(raw.children);
	if (raw.type === 'colonnes') block.columns = Array.isArray(raw.columns) ? raw.columns.map(readBlocks) : [];
	return block;
}
