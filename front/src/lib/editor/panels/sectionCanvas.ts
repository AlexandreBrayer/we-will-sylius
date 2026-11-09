// Sections in the canvas of the builder (D12), through the public API of GrapesJS only:
//  - the selected blocks, as they are saved (props and style, page order, layout containers with their
//    content), and the labels of the blocks bound to the template among them (they are not saved as a section);
//  - inserting a section: its blocks become new components, built exactly as when the editor opens a
//    content without project data (revisionCanvas.ts `blockComponents`): a copy, no link to the section;
//  - dragging a section from « Mes sections » into the page: a GrapesJS block made for the drag (not
//    added to the palette of GrapesJS), started and ended by the Blocks API of the editor.
import type { Block, Component as GjsComponent, Editor } from 'grapesjs';
import { blockDefinition } from '#lib/blocks/registry.js';
import type { PageBlock } from '#lib/blocks/types.js';
import { componentBlock, pageComponents, type BlockTree } from '#lib/editor/layouts.js';
import { blockComponents } from '#lib/editor/panels/revisionCanvas.js';

/** Blocks of the selection: free blocks as they are saved, and the labels of the bound ones. */
export interface SelectedBlocks {
	blocks: PageBlock[];
	bound: string[];
}

/**
 * Selected blocks of the page, in page order (product blocks of a template in their zone; components that
 * are not Yack blocks left out). A selected container brings its content: a block inside it, selected too,
 * is not taken twice.
 */
export function selectedBlocks(editor: Editor): SelectedBlocks {
	const wrapper = editor.getWrapper();
	if (!wrapper) return { blocks: [], bound: [] };
	const order: GjsComponent[] = pageComponents(wrapper);
	const all = editor.getSelectedAll();
	const selected = all
		.filter((component) => order.includes(component) && !component.parents().some((parent) => all.includes(parent)))
		.sort((a, b) => order.indexOf(a) - order.indexOf(b));
	const blocks: PageBlock[] = [];
	const bound: string[] = [];
	for (const component of selected) {
		const type: unknown = component.get('type');
		const definition = typeof type === 'string' ? blockDefinition(type) : undefined;
		if (!definition) continue;
		if (definition.scope !== null) {
			if (!bound.includes(definition.label)) bound.push(definition.label);
			continue;
		}
		const block = componentBlock(component);
		if (block) blocks.push(block);
	}
	return { blocks, bound };
}

/** Components of the blocks of a section (free blocks only: no mandatory block added). */
const sectionContent = (blocks: readonly BlockTree[]) => blockComponents(blocks, null);

/**
 * Appends a copy of the blocks at the end of the page, selects the first one and shows it. One change of
 * the editor: Ctrl+Z takes the copy out again.
 */
export function insertSection(editor: Editor, blocks: readonly BlockTree[]): GjsComponent[] {
	const wrapper = editor.getWrapper();
	if (!wrapper) return [];
	const added = wrapper.append(sectionContent(blocks));
	const [first] = added;
	if (first) {
		editor.select(first);
		first.getEl()?.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}
	return added;
}

/** GrapesJS block of a drag (the collection of the palette builds it; it is not added to the palette). */
function dragBlock(editor: Editor, id: number, blocks: readonly BlockTree[]): Block {
	const BlockModel = editor.Blocks.getAll().model as unknown as new (attributes: Record<string, unknown>) => Block;
	// Not selected after the drop: the selection would come one tick after the copy and take its own step in
	// the undo history (GrapesJS records the selection), so the first Ctrl+Z would only deselect.
	return new BlockModel({ id: `yack-section-${id}`, content: sectionContent(blocks), select: false, activate: false });
}

/**
 * Starts dragging a section (native `dragstart` of its line in the palette): the canvas shows where its
 * blocks go and drops a copy of them. `ondrop` is called when the copy lands in the page (not when the drag
 * is cancelled or ends outside the canvas). Returns the function the `dragend` of the line calls.
 */
export function startSectionDrag(editor: Editor, id: number, blocks: readonly BlockTree[], event: DragEvent, ondrop: () => void): (event: DragEvent) => void {
	const block = dragBlock(editor, id, blocks);
	// Firefox starts a native drag only with data; the canvas reads the content of the block, not this text.
	event.dataTransfer?.setData('text/plain', '');
	if (event.dataTransfer) event.dataTransfer.effectAllowed = 'copy';
	const stopped = (result: unknown, dragged: unknown) => {
		if (dragged !== block) return;
		editor.off('block:drag:stop', stopped);
		if (result) ondrop();
	};
	editor.on('block:drag:stop', stopped);
	editor.Blocks.startDrag(block, event);
	return (end: DragEvent) => {
		editor.Blocks.endDrag(end.dataTransfer?.dropEffect === 'none');
		editor.off('block:drag:stop', stopped);
	};
}
