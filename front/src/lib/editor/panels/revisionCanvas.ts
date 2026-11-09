// A revision in the canvas of the builder (D11), through the public API of GrapesJS only:
//  - preview: the components of the edited content are taken out of the page as they are (same models,
//    so their undo history stays valid), the blocks of the revision are shown locked (not selectable,
//    not movable, not removable; the page not selectable), then the edited components are put back.
//    Nothing of it is recorded by the undo manager nor counted as a change (`avoidStore`), and undo /
//    redo shortcuts are held while the revision is shown;
//  - restore: the blocks of the revision replace the page as one change of the editor (recorded, so
//    Ctrl+Z gives the previous content back), not saved: the next save creates a new revision.
// Blocks become components exactly as when the editor opens a content without project data
// (grapesSetup.ts, layouts.ts `blockComponent`): `{ type, attributes: props + yb-<style> }`, layout
// containers with their content, mandatory blocks of a template added, product blocks of a template in
// their product zones (zones.ts), so that the zones are already as the editor keeps them: nothing is
// regrouped after the preview or the restore.
import type { Component as GjsComponent, ComponentDefinition, Editor } from 'grapesjs';
import { allowedBlocks, blockDefinition } from '#lib/blocks/registry.js';
import type { TemplateType } from '#lib/blocks/types.js';
import { blockComponent, type BlockTree } from '#lib/editor/layouts.js';
import { zonedComponents } from '#lib/editor/zones.js';

/** Component props of a block shown in the preview: nothing can be selected, moved, dropped or removed. */
const READ_ONLY = {
	selectable: false,
	hoverable: false,
	highlightable: false,
	draggable: false,
	droppable: false,
	removable: false,
	copyable: false,
	layerable: false,
	badgable: false,
	editable: false
};

/**
 * Components of the blocks of a revision (types unknown to the registry dropped), plus the mandatory
 * blocks of the template type when the revision lacks them, as the editor does at opening; product
 * blocks grouped in their zones. `extra` goes to every component, zones and columns included.
 */
export function blockComponents(blocks: readonly BlockTree[], templateType: TemplateType | null, extra: ComponentDefinition = {}): ComponentDefinition[] {
	const known = blocks.filter((block) => blockDefinition(block.type) !== undefined);
	// A missing mandatory block takes the defaults of its type (no attributes given).
	const added = new Set<BlockTree>();
	for (const entry of allowedBlocks(templateType)) {
		if (!entry.mandatory || known.some((block) => block.type === entry.type)) continue;
		const block: BlockTree = { type: entry.type, props: {} };
		added.add(block);
		known.push(block);
	}
	return zonedComponents(known, (block) => (added.has(block) ? { type: block.type, ...extra } : blockComponent(block, extra)), extra);
}

/**
 * Options of the collection calls: not counted as a change of the editor (`avoidStore`, read by GrapesJS
 * but absent from its types); `temporary` keeps a component intact out of the page, as a move does.
 */
const QUIET = { avoidStore: true, silent: false };
const ASIDE = { ...QUIET, temporary: true };

/** Undo / redo shortcuts (Ctrl or Cmd + Z, Shift + Z, Y). */
const isHistoryShortcut = (event: KeyboardEvent): boolean =>
	(event.ctrlKey || event.metaKey) && !event.altKey && ['z', 'y'].includes(event.key.toLowerCase());

export interface RevisionPreview {
	/** Shows (or replaces) the blocks of a revision, locked. */
	show(blocks: readonly BlockTree[]): void;
	/** Puts the edited components back as they were (selection included) and ends the preview. */
	close(): void;
}

/**
 * Starts a preview: the edited components leave the page untouched until `close()`. While it lasts,
 * the page of the canvas is inert, and undo and redo shortcuts are stopped in the builder and in the
 * canvas (the undo history refers to the edited components, which are out of the page).
 */
export function openPreview(editor: Editor, templateType: TemplateType | null): RevisionPreview {
	const undo = editor.UndoManager;
	const wrapper = editor.getWrapper()!;
	const components = wrapper.components();
	const kept: GjsComponent[] = [...components.models];
	const selected = editor.getSelectedAll();
	// The page itself (wrapper) is not selectable either: a click on a locked block would select it.
	const wrapperProps = { droppable: wrapper.get('droppable'), selectable: wrapper.get('selectable'), hoverable: wrapper.get('hoverable') };
	const frameWindow = editor.Canvas.getWindow();
	// The page of the canvas is inert while a revision is shown: no focus, no click, no double click, so
	// nothing inside a block can start an edit (in-place text editing of the blocks, D8: B3-1) on the
	// blocks of the revision. Scrolling stays possible.
	const frameBody = editor.Canvas.getBody();
	const bodyWasInert = frameBody?.inert ?? false;

	const hold = (event: KeyboardEvent) => {
		if (!isHistoryShortcut(event)) return;
		event.preventDefault();
		event.stopImmediatePropagation();
	};
	window.addEventListener('keydown', hold, true);
	frameWindow?.addEventListener('keydown', hold, true);

	if (frameBody) frameBody.inert = true;
	undo.skip(() => {
		if (selected.length > 0) editor.selectRemove(selected);
		wrapper.set({ droppable: false, selectable: false, hoverable: false }, QUIET);
		components.remove(kept, ASIDE);
	});

	let open = true;
	return {
		show(blocks) {
			if (!open) return;
			undo.skip(() => {
				components.reset(blockComponents(blocks, templateType, READ_ONLY), QUIET);
			});
		},
		close() {
			if (!open) return;
			open = false;
			undo.skip(() => {
				components.reset([], QUIET);
				components.add(kept, QUIET);
				wrapper.set(wrapperProps, QUIET);
				if (selected.length > 0) editor.select(selected);
			});
			if (frameBody) frameBody.inert = bodyWasInert;
			window.removeEventListener('keydown', hold, true);
			frameWindow?.removeEventListener('keydown', hold, true);
		}
	};
}

/**
 * Replaces the content of the editor by the blocks of a revision, as one change: the editor marks it
 * as modified, Ctrl+Z gives the previous content back, nothing is saved.
 */
export function restoreRevision(editor: Editor, blocks: readonly BlockTree[], templateType: TemplateType | null): void {
	editor.getWrapper()!.components(blockComponents(blocks, templateType));
}
