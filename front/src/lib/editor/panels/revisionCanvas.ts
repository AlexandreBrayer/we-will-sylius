// A revision in the canvas of the builder (D11), through the public API of GrapesJS only:
//  - preview: the components of the edited content are taken out of the page as they are (same models,
//    so their undo history stays valid), the blocks of the revision are shown locked (not selectable,
//    not movable, not removable; the page not selectable), then the edited components are put back.
//    Nothing of it is recorded by the undo manager nor counted as a change (`avoidStore`), and undo /
//    redo shortcuts are held while the revision is shown;
//  - restore: the blocks of the revision replace the page as one change of the editor (recorded, so
//    Ctrl+Z gives the previous content back), not saved: the next save creates a new revision.
// Blocks become components exactly as when the editor opens a content without project data
// (grapesSetup.ts): `{ type, attributes: props + yb-<style> }`, mandatory blocks of a template added.
import type { Component as GjsComponent, Editor } from 'grapesjs';
import { allowedBlocks, blockDefinition } from '#lib/blocks/registry.js';
import type { TemplateType } from '#lib/blocks/types.js';
import { styleAttributes } from '#lib/editor/panels/blockStyle.js';
import type { RevisionBlock } from '#lib/editor/panels/history.js';

type ComponentDefinition = Record<string, unknown> & { type: string };

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
 * blocks of the template type when the revision lacks them, as the editor does at opening.
 */
export function blockComponents(blocks: readonly RevisionBlock[], templateType: TemplateType | null, extra: Record<string, unknown> = {}): ComponentDefinition[] {
	const components: ComponentDefinition[] = blocks
		.filter((block) => blockDefinition(block.type) !== undefined)
		.map((block) => ({ type: block.type, attributes: { ...block.props, ...styleAttributes(block.style) }, ...extra }));
	for (const entry of allowedBlocks(templateType)) {
		if (entry.mandatory && !components.some((component) => component.type === entry.type)) components.push({ type: entry.type, ...extra });
	}
	return components;
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
	show(blocks: readonly RevisionBlock[]): void;
	/** Puts the edited components back as they were (selection included) and ends the preview. */
	close(): void;
}

/**
 * Starts a preview: the edited components leave the page untouched until `close()`. While it lasts,
 * undo and redo shortcuts are stopped in the builder and in the canvas (the undo history refers to the
 * edited components, which are out of the page).
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

	const hold = (event: KeyboardEvent) => {
		if (!isHistoryShortcut(event)) return;
		event.preventDefault();
		event.stopImmediatePropagation();
	};
	window.addEventListener('keydown', hold, true);
	frameWindow?.addEventListener('keydown', hold, true);

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
			window.removeEventListener('keydown', hold, true);
			frameWindow?.removeEventListener('keydown', hold, true);
		}
	};
}

/**
 * Replaces the content of the editor by the blocks of a revision, as one change: the editor marks it
 * as modified, Ctrl+Z gives the previous content back, nothing is saved.
 */
export function restoreRevision(editor: Editor, blocks: readonly RevisionBlock[], templateType: TemplateType | null): void {
	editor.getWrapper()!.components(blockComponents(blocks, templateType));
}
