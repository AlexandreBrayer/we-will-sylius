// Canvas of a read-only editor (edit lock of another administrator, D13), through the public API of
// GrapesJS only, as the preview of a revision does (panels/revisionCanvas.ts) but on the edited components
// themselves: nothing is selected, the page cannot be selected nor receive a block, the page of the canvas
// is inert (no click, no double click: no text typed in place, D8; no drag), and the editing shortcuts are
// held (undo, redo, cut, paste, duplicate, delete, Ctrl+S). Nothing of it is recorded by the undo
// manager nor counted as a change. The palette, the sections and the panels are made inert by the shell.
import type { Editor } from 'grapesjs';

/** Not counted as a change of the editor (`avoidStore`, read by GrapesJS but absent from its types). */
const QUIET = { avoidStore: true, silent: false };

/** A field where Delete and Backspace type (read by shape: the canvas is another window, another HTMLElement). */
export function isTextField(target: EventTarget | null): boolean {
	const element = target as { tagName?: unknown; isContentEditable?: unknown } | null;
	if (element === null || typeof element !== 'object') return false;
	return element.isContentEditable === true || (typeof element.tagName === 'string' && ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName));
}

/** Shortcuts that change the content (or save it) in the builder and in GrapesJS. */
export function isEditShortcut(event: Pick<KeyboardEvent, 'key' | 'ctrlKey' | 'metaKey' | 'altKey' | 'target'>): boolean {
	const key = event.key.toLowerCase();
	if ((event.ctrlKey || event.metaKey) && !event.altKey) return ['z', 'y', 'x', 'v', 'd', 's'].includes(key);
	return (key === 'delete' || key === 'backspace') && !isTextField(event.target);
}

/** Makes the canvas read-only; the function returned gives it back as it was. */
export function lockCanvas(editor: Editor): () => void {
	const wrapper = editor.getWrapper();
	const frameWindow = editor.Canvas.getWindow();
	const frameBody = editor.Canvas.getBody();
	const wrapperProps = wrapper ? { droppable: wrapper.get('droppable'), selectable: wrapper.get('selectable'), hoverable: wrapper.get('hoverable') } : null;
	const bodyWasInert = frameBody?.inert ?? false;

	const hold = (event: KeyboardEvent) => {
		if (!isEditShortcut(event)) return;
		event.preventDefault();
		event.stopImmediatePropagation();
	};
	window.addEventListener('keydown', hold, true);
	frameWindow?.addEventListener('keydown', hold, true);
	if (frameBody) frameBody.inert = true;
	editor.UndoManager.skip(() => {
		const selected = editor.getSelectedAll();
		if (selected.length > 0) editor.selectRemove(selected);
		wrapper?.set({ droppable: false, selectable: false, hoverable: false }, QUIET);
	});

	let locked = true;
	return () => {
		if (!locked) return;
		locked = false;
		window.removeEventListener('keydown', hold, true);
		frameWindow?.removeEventListener('keydown', hold, true);
		if (frameBody) frameBody.inert = bodyWasInert;
		if (wrapper && wrapperProps) editor.UndoManager.skip(() => wrapper.set(wrapperProps, QUIET));
	};
}
