// GrapesJS glue for the builder's component tree (replaces the Layer Manager): read-only snapshot of
// the component model for the Svelte panel, tree actions going through the GrapesJS model (so the
// UndoManager records them) and the padlock on mandatory blocks in the canvas toolbar.
import type { Component, Editor, ToolbarButtonProps } from 'grapesjs';
import { REGISTRY } from '#lib/blocks/registry.js';

export interface TreeNode {
	/** Backbone `cid` of the GrapesJS component: stable for the model's lifetime (moves included). */
	id: string;
	type: string;
	label: string;
	/** Distinguishes blocks of the same type (their title, if any). */
	detail: string;
	icon: string | null;
	/** Mandatory block of the template (`yackMandatory`): padlock, neither removable nor copyable. */
	locked: boolean;
	removable: boolean;
	copyable: boolean;
	draggable: boolean;
	children: TreeNode[];
}

export interface TreeSnapshot {
	nodes: TreeNode[];
	byId: Map<string, Component>;
}

/** Event triggered on the editor when a locked block is the target of a forbidden action. */
export const LOCKED_EVENT = 'yack:locked';
const LOCKED_COMMAND = 'yack:locked';
const LOCK_CLASS = 'yb-tlb-lock';

export const LOCK_ICON =
	'<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';

const ICONS = new Map(REGISTRY.map((entry) => [entry.type as string, entry.icon]));

export const isLocked = (component: Component): boolean => component.get('yackMandatory') === true;

function toNode(component: Component, byId: Map<string, Component>): TreeNode {
	byId.set(component.cid, component);
	const type = String(component.get('type') ?? '');
	const title = component.getAttributes().title;
	return {
		id: component.cid,
		type,
		label: component.getName(),
		detail: typeof title === 'string' ? title.trim() : '',
		icon: ICONS.get(type) ?? null,
		locked: isLocked(component),
		removable: component.get('removable') !== false,
		copyable: component.get('copyable') !== false,
		draggable: component.get('draggable') !== false,
		children: component.components().map((child: Component) => toNode(child, byId))
	};
}

/** Page blocks (children of the wrapper, which is not shown) and an id → component index. */
export function snapshot(editor: Editor): TreeSnapshot {
	const byId = new Map<string, Component>();
	const wrapper = editor.getWrapper();
	return { nodes: wrapper ? wrapper.components().map((child: Component) => toNode(child, byId)) : [], byId };
}

/** `parent` accepts `source` at `index` (nesting rules: `droppable` / `draggable` of the models). */
export function canDrop(editor: Editor, source: Component, parent: Component, index: number): boolean {
	if (parent === source || parent.parents().includes(source)) return false;
	return editor.Components.canMove(parent, source, index).result;
}

/** Moves `component` one step among its siblings; `move` counts `at` before removing the component. */
export function moveBy(component: Component, delta: -1 | 1): boolean {
	const parent = component.parent();
	if (!parent) return false;
	const index = component.index();
	const target = index + delta;
	if (target < 0 || target >= parent.components().length) return false;
	component.move(parent, { at: delta > 0 ? index + 2 : target });
	return true;
}

export function duplicate(editor: Editor, component: Component): Component | null {
	const parent = component.parent();
	if (!parent || component.get('copyable') === false || isLocked(component)) {
		editor.trigger(LOCKED_EVENT, component);
		return null;
	}
	const [clone] = parent.append(component.clone(), { at: component.index() + 1 });
	return clone ?? null;
}

/** Same command as the canvas (Delete key, toolbar): refuses non-removable components. */
export function remove(editor: Editor, component: Component): boolean {
	if (component.get('removable') === false || isLocked(component)) {
		editor.trigger(LOCKED_EVENT, component);
		return false;
	}
	editor.runCommand('core:component-delete', { component });
	return true;
}

export function select(editor: Editor, component: Component): void {
	editor.select(component);
	editor.Canvas.scrollTo(component, { behavior: 'smooth', block: 'nearest' });
}

export function hover(editor: Editor, component: Component | null): void {
	editor.getModel().setHovered(component ?? null, { forceChange: true });
}

/** Canvas toolbar without the actions the component forbids, plus the padlock if it is mandatory. */
function lockToolbar(component: Component): void {
	const toolbar = (component.get('toolbar') ?? []) as ToolbarButtonProps[];
	const removable = component.get('removable') !== false && !isLocked(component);
	const copyable = component.get('copyable') !== false && !isLocked(component);
	const next = toolbar.filter(
		(item) =>
			!(item.attributes as Record<string, unknown> | undefined)?.class?.toString().includes(LOCK_CLASS) &&
			!(item.command === 'tlb-delete' && !removable) &&
			!(item.command === 'tlb-clone' && !copyable)
	);
	if (isLocked(component)) {
		next.push({
			label: LOCK_ICON,
			command: LOCKED_COMMAND,
			attributes: { class: LOCK_CLASS, title: 'Bloc obligatoire : ni suppression ni duplication' }
		});
	}
	if (next.length !== toolbar.length || next.some((item, i) => item !== toolbar[i])) component.set('toolbar', next);
}

/**
 * Canvas side of the tree: padlock in the toolbar of mandatory blocks and `LOCKED_EVENT` when the Delete
 * key or the padlock hits one. Call once after `initEditor`; returns the teardown.
 */
export function installTree(editor: Editor): () => void {
	const apply = (component: Component) => {
		lockToolbar(component);
		component.components().forEach(apply);
	};
	const onAdd = (component: Component) => apply(component);
	const onLockChange = (component: Component) => lockToolbar(component);
	const onKeymap = (id: string) => {
		if (id !== 'core:component-delete') return;
		const locked = editor.getSelectedAll().find((component) => component.get('removable') === false || isLocked(component));
		if (locked) editor.trigger(LOCKED_EVENT, locked);
	};

	editor.Commands.add(LOCKED_COMMAND, {
		run(ed) {
			ed.trigger(LOCKED_EVENT, ed.getSelected());
		}
	});
	const wrapper = editor.getWrapper();
	if (wrapper) wrapper.components().forEach(apply);
	editor.on('component:add', onAdd);
	editor.on('component:update:yackMandatory component:update:removable component:update:copyable', onLockChange);
	editor.on('keymap:emit', onKeymap);

	return () => {
		editor.off('component:add', onAdd);
		editor.off('component:update:yackMandatory component:update:removable component:update:copyable', onLockChange);
		editor.off('keymap:emit', onKeymap);
	};
}
