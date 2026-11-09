<!--
	Builder component tree (replaces the GrapesJS Layer Manager), bound to the GrapesJS model: selection and
	hover synced with the canvas, drag & drop (pointer events: mouse anywhere on the row, touch on the grip)
	following the nesting rules of the models, move / duplicate / delete, mandatory blocks locked, keyboard.
	Every change goes through the GrapesJS model, so the UndoManager (buttons, Ctrl+Z) covers it.
-->
<script lang="ts">
	import { tick } from 'svelte';
	import { SvelteSet } from 'svelte/reactivity';
	import type { Component, Editor } from 'grapesjs';
	import {
		LOCKED_EVENT,
		LOCK_ICON,
		canDrop,
		duplicate,
		hover,
		moveBy,
		remove,
		select,
		snapshot,
		type TreeNode
	} from './componentTree.js';

	let { editor }: { editor: Editor | null } = $props();

	interface Row {
		node: TreeNode;
		depth: number;
		parentId: string | null;
		index: number;
		last: boolean;
	}
	type Position = 'before' | 'after' | 'inside' | 'end';
	interface Drop {
		overId: string | null;
		position: Position;
		parent: Component;
		index: number;
		valid: boolean;
	}
	interface Press {
		id: string;
		x: number;
		y: number;
		pointerType: string;
	}

	let nodes = $state.raw<TreeNode[]>([]);
	let byId = new Map<string, Component>();
	let selectedIds = $state.raw<string[]>([]);
	let hoveredId = $state<string | null>(null);
	let focusedId = $state<string | null>(null);
	const collapsed = new SvelteSet<string>();
	let canUndo = $state(false);
	let canRedo = $state(false);
	let notice = $state<string | null>(null);
	let noticeTimer: ReturnType<typeof setTimeout> | undefined;
	let press: Press | null = null;
	let dragging = $state<{ id: string; label: string; x: number; y: number } | null>(null);
	let drop = $state.raw<Drop | null>(null);
	let rootEl: HTMLDivElement;
	let treeEl = $state<HTMLUListElement>();

	const rows = $derived.by(() => {
		const out: Row[] = [];
		const walk = (list: TreeNode[], depth: number, parentId: string | null) =>
			list.forEach((node, index) => {
				out.push({ node, depth, parentId, index, last: index === list.length - 1 });
				if (node.children.length > 0 && !collapsed.has(node.id)) walk(node.children, depth + 1, node.id);
			});
		walk(nodes, 1, null);
		return out;
	});
	const tabbableId = $derived(rows.some((row) => row.node.id === focusedId) ? focusedId : (selectedIds[0] ?? rows[0]?.node.id ?? null));

	const rowEl = (id: string) => treeEl?.querySelector<HTMLElement>(`[data-tree-row="${id}"]`) ?? null;

	function showNotice(text: string) {
		notice = text;
		clearTimeout(noticeTimer);
		noticeTimer = setTimeout(() => (notice = null), 5000);
	}

	$effect(() => {
		const ed = editor;
		if (!ed) return;
		let queued = false;
		const refresh = () => {
			if (queued) return;
			queued = true;
			queueMicrotask(async () => {
				queued = false;
				const hadFocus = !!treeEl && treeEl.contains(document.activeElement);
				const tree = snapshot(ed);
				byId = tree.byId;
				nodes = tree.nodes;
				selectedIds = ed.getSelectedAll().map((component) => component.cid);
				canUndo = ed.UndoManager.hasUndo();
				canRedo = ed.UndoManager.hasRedo();
				if (!hadFocus) return;
				// Keyed rows moved in the DOM lose the focus: give it back to the same block (or the selection).
				await tick();
				const id = focusedId && byId.has(focusedId) ? focusedId : (selectedIds[0] ?? null);
				if (id && document.activeElement !== rowEl(id)) rowEl(id)?.focus({ preventScroll: true });
			});
		};
		const onToggled = () => {
			selectedIds = ed.getSelectedAll().map((component) => component.cid);
			const first = selectedIds[0];
			if (!first) return;
			focusedId = first;
			tick().then(() => rowEl(first)?.scrollIntoView({ block: 'nearest' }));
		};
		const onHovered = (component: Component) => (hoveredId = component.cid);
		const onUnhovered = () => (hoveredId = null);
		const onLocked = (component?: Component) =>
			showNotice(
				`« ${component?.getName() ?? 'Ce bloc'} » est obligatoire : il peut être déplacé, mais ni supprimé ni dupliqué.`
			);
		const changes = 'component:add component:remove component:update undo redo update load';
		ed.on(changes, refresh);
		ed.on('component:toggled', onToggled);
		ed.on('component:hovered', onHovered);
		ed.on('component:unhovered', onUnhovered);
		ed.on(LOCKED_EVENT, onLocked);
		refresh();
		return () => {
			ed.off(changes, refresh);
			ed.off('component:toggled', onToggled);
			ed.off('component:hovered', onHovered);
			ed.off('component:unhovered', onUnhovered);
			ed.off(LOCKED_EVENT, onLocked);
		};
	});

	function selectNode(id: string) {
		const component = byId.get(id);
		if (!editor || !component) return;
		focusedId = id;
		select(editor, component);
	}

	function focusNode(id: string | undefined) {
		if (!id || !editor) return;
		focusedId = id;
		rowEl(id)?.focus();
		hover(editor, byId.get(id) ?? null);
	}

	function act(id: string, action: 'up' | 'down' | 'duplicate' | 'delete') {
		const component = byId.get(id);
		if (!editor || !component) return;
		focusedId = id;
		if (action === 'up' || action === 'down') {
			moveBy(component, action === 'up' ? -1 : 1);
		} else if (action === 'duplicate') {
			const clone = duplicate(editor, component);
			if (clone) select(editor, clone);
		} else {
			const position = rows.findIndex((row) => row.node.id === id);
			const neighbour = rows[position + 1]?.parentId === rows[position]?.parentId ? rows[position + 1] : rows[position - 1];
			if (remove(editor, component) && neighbour) {
				focusedId = neighbour.node.id;
				const next = byId.get(neighbour.node.id);
				if (next) select(editor, next);
			}
		}
	}

	function onKeydown(event: KeyboardEvent) {
		if (!editor) return;
		const position = rows.findIndex((row) => row.node.id === tabbableId);
		const row = rows[position];
		const onButton = (event.target as HTMLElement).closest('button') !== null;
		const mod = event.ctrlKey || event.metaKey;
		const key = event.key;
		if (key === 'Escape' && dragging) {
			endDrag(false);
		} else if (mod && key.toLowerCase() === 'z') {
			if (event.shiftKey) editor.UndoManager.redo();
			else editor.UndoManager.undo();
		} else if (mod && key.toLowerCase() === 'y') {
			editor.UndoManager.redo();
		} else if (!row) {
			return;
		} else if (event.altKey && (key === 'ArrowUp' || key === 'ArrowDown')) {
			act(row.node.id, key === 'ArrowUp' ? 'up' : 'down');
		} else if (key === 'ArrowDown') {
			focusNode(rows[position + 1]?.node.id);
		} else if (key === 'ArrowUp') {
			focusNode(rows[position - 1]?.node.id);
		} else if (key === 'Home') {
			focusNode(rows[0]?.node.id);
		} else if (key === 'End') {
			focusNode(rows.at(-1)?.node.id);
		} else if (key === 'ArrowRight' && row.node.children.length > 0) {
			if (collapsed.has(row.node.id)) collapsed.delete(row.node.id);
			else focusNode(row.node.children[0]?.id);
		} else if (key === 'ArrowLeft') {
			if (row.node.children.length > 0 && !collapsed.has(row.node.id)) collapsed.add(row.node.id);
			else focusNode(row.parentId ?? undefined);
		} else if ((key === 'Enter' || key === ' ') && !onButton) {
			selectNode(row.node.id);
		} else if (key === 'Delete' || key === 'Backspace') {
			act(row.node.id, 'delete');
		} else if (mod && key.toLowerCase() === 'd') {
			act(row.node.id, 'duplicate');
		} else {
			return;
		}
		// Handled here: GrapesJS keymaps (listening on the document) must not run it a second time.
		event.preventDefault();
		event.stopPropagation();
	}

	// ── Drag & drop ────────────────────────────────────────────────────────────────────────────────

	function onRowPointerDown(event: PointerEvent, node: TreeNode) {
		const target = event.target as HTMLElement;
		if (event.button !== 0 || target.closest('button')) return;
		// Touch / pen: only from the grip, the rest of the row keeps scrolling the panel.
		if (event.pointerType !== 'mouse' && !target.closest('.grip')) return;
		press = { id: node.id, x: event.clientX, y: event.clientY, pointerType: event.pointerType };
		window.addEventListener('pointermove', onPointerMove);
		window.addEventListener('pointerup', onPointerUp);
		window.addEventListener('pointercancel', onPointerCancel);
	}

	function onPointerMove(event: PointerEvent) {
		if (!press || !editor) return;
		if (!dragging) {
			if (Math.hypot(event.clientX - press.x, event.clientY - press.y) < 5) return;
			const component = byId.get(press.id);
			if (!component || component.get('draggable') === false) return;
			dragging = { id: press.id, label: component.getName(), x: event.clientX, y: event.clientY };
			hover(editor, null);
		}
		event.preventDefault();
		dragging.x = event.clientX;
		dragging.y = event.clientY;
		autoScroll(event.clientY);
		drop = dropAt(event.clientX, event.clientY);
	}

	function dropAt(x: number, y: number): Drop | null {
		const source = dragging && byId.get(dragging.id);
		const wrapper = editor?.getWrapper();
		if (!editor || !source || !wrapper) return null;
		const el = document.elementFromPoint(x, y);
		const row = el?.closest<HTMLElement>('[data-tree-row]');
		const target = row && byId.get(row.dataset.treeRow ?? '');
		if (!row || !target) {
			if (!el || !rootEl.contains(el)) return null;
			const index = wrapper.components().length;
			return { overId: null, position: 'end', parent: wrapper, index, valid: canDrop(editor, source, wrapper, index) };
		}
		const rect = row.getBoundingClientRect();
		const ratio = (y - rect.top) / rect.height;
		const childCount = target.components().length;
		if (ratio > 0.25 && ratio < 0.75 && target !== source && canDrop(editor, source, target, childCount)) {
			return { overId: target.cid, position: 'inside', parent: target, index: childCount, valid: true };
		}
		const parent = target.parent() ?? wrapper;
		const position = ratio < 0.5 ? 'before' : 'after';
		const index = target.index() + (position === 'after' ? 1 : 0);
		return { overId: target.cid, position, parent, index, valid: canDrop(editor, source, parent, index) };
	}

	function autoScroll(y: number) {
		let scroller: HTMLElement | null = rootEl;
		while (scroller && !(scroller.scrollHeight > scroller.clientHeight && /(auto|scroll)/.test(getComputedStyle(scroller).overflowY))) {
			scroller = scroller.parentElement;
		}
		if (!scroller) return;
		const rect = scroller.getBoundingClientRect();
		if (y < rect.top + 32) scroller.scrollTop -= 12;
		else if (y > rect.bottom - 32) scroller.scrollTop += 12;
	}

	function onPointerUp() {
		if (dragging) endDrag(true);
		else if (press) selectNode(press.id);
		cleanupPointer();
	}

	function onPointerCancel() {
		endDrag(false);
		cleanupPointer();
	}

	function endDrag(commit: boolean) {
		const component = dragging && byId.get(dragging.id);
		if (commit && editor && component && drop?.valid) {
			component.move(drop.parent, { at: drop.index });
			focusedId = component.cid;
			select(editor, component);
			rowEl(component.cid)?.focus({ preventScroll: true });
		}
		dragging = null;
		drop = null;
		press = null;
	}

	function cleanupPointer() {
		press = null;
		window.removeEventListener('pointermove', onPointerMove);
		window.removeEventListener('pointerup', onPointerUp);
		window.removeEventListener('pointercancel', onPointerCancel);
	}

	$effect(() => cleanupPointer);
</script>

<div class="tree-panel" bind:this={rootEl} class:is-dragging={dragging !== null}>
	<div class="tree-head">
		<span class="count">{nodes.length} bloc{nodes.length > 1 ? 's' : ''}</span>
		<span class="spacer"></span>
		<button class="tool" title="Annuler (Ctrl+Z)" aria-label="Annuler" disabled={!canUndo} onclick={() => editor?.UndoManager.undo()}>
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 14 4 9l5-5" /><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11" /></svg>
		</button>
		<button class="tool" title="Rétablir (Ctrl+Maj+Z)" aria-label="Rétablir" disabled={!canRedo} onclick={() => editor?.UndoManager.redo()}>
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 14 5-5-5-5" /><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13" /></svg>
		</button>
	</div>

	{#if notice}
		<div class="notice" role="status">
			<span class="lock">{@html LOCK_ICON}</span>
			<span>{notice}</span>
			<button aria-label="Fermer" onclick={() => (notice = null)}>×</button>
		</div>
	{/if}

	{#if nodes.length === 0}
		<p class="empty">Aucun bloc pour l'instant : ajoutez-en depuis l'onglet « Blocs ».</p>
	{:else}
		<ul
			class="tree"
			role="tree"
			aria-label="Structure de la page"
			bind:this={treeEl}
			onkeydown={onKeydown}
			onpointerleave={() => !dragging && editor && hover(editor, null)}
		>
			{@render branch(nodes, 1)}
		</ul>
		<p class="hint">Glissez une ligne pour déplacer un bloc. Clavier : ↑ ↓ naviguer, Entrée sélectionner, Alt+↑ ↓ déplacer, Suppr supprimer, Ctrl+D dupliquer.</p>
	{/if}
</div>

{#snippet branch(list: TreeNode[], depth: number)}
	{#each list as node, index (node.id)}
		{@const open = node.children.length > 0 && !collapsed.has(node.id)}
		<li role="none">
			<div
				class="row {drop?.overId === node.id ? `drop-${drop.position}${drop.valid ? '' : ' drop-invalid'}` : ''}"
				class:selected={selectedIds.includes(node.id)}
				class:hovered={hoveredId === node.id}
				class:locked={node.locked}
				class:dragged={dragging?.id === node.id}
				style:--depth={depth - 1}
				data-tree-row={node.id}
				role="treeitem"
				aria-level={depth}
				aria-selected={selectedIds.includes(node.id)}
				aria-expanded={node.children.length > 0 ? open : undefined}
				aria-labelledby={node.offCharte ? `tree-label-${node.id} tree-off-${node.id}` : `tree-label-${node.id}`}
				tabindex={tabbableId === node.id ? 0 : -1}
				onfocus={() => (focusedId = node.id)}
				onpointerdown={(event) => onRowPointerDown(event, node)}
				onpointerenter={() => !dragging && editor && hover(editor, byId.get(node.id) ?? null)}
			>
				<span class="grip" aria-hidden="true" title="Glisser pour déplacer">
					<svg viewBox="0 0 24 24"><circle cx="9" cy="6" r="1.4" /><circle cx="15" cy="6" r="1.4" /><circle cx="9" cy="12" r="1.4" /><circle cx="15" cy="12" r="1.4" /><circle cx="9" cy="18" r="1.4" /><circle cx="15" cy="18" r="1.4" /></svg>
				</span>
				{#if node.children.length > 0}
					<button
						class="toggle"
						aria-label={open ? 'Replier' : 'Déplier'}
						tabindex="-1"
						onclick={() => (open ? collapsed.add(node.id) : collapsed.delete(node.id))}
					>
						<svg viewBox="0 0 24 24" class:open aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg>
					</button>
				{/if}
				<span class="icon">
					{#if node.icon}<span class="glyph" aria-hidden="true">{@html node.icon}</span>{/if}
					{#if node.locked}
						<span class="lock-badge" role="img" title="Bloc obligatoire : ni suppression ni duplication" aria-label="Bloc obligatoire">{@html LOCK_ICON}</span>
					{/if}
				</span>
				<span class="label" id="tree-label-{node.id}">
					<strong>{node.label}</strong>
					{#if node.detail}<small>{node.detail}</small>{/if}
				</span>
				{#if node.offCharte}
					<span class="off-charte" id="tree-off-{node.id}" role="img" title="Hors charte : {node.offCharte}" aria-label="Hors charte : {node.offCharte}" data-test-off-charte>≠</span>
				{/if}
				<span class="actions">
					<button tabindex="-1" title="Monter" aria-label="Monter {node.label}" disabled={index === 0} onclick={() => act(node.id, 'up')}>
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 15 6-6 6 6" /></svg>
					</button>
					<button tabindex="-1" title="Descendre" aria-label="Descendre {node.label}" disabled={index === list.length - 1} onclick={() => act(node.id, 'down')}>
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
					</button>
					{#if !node.locked}
						<button tabindex="-1" title="Dupliquer" aria-label="Dupliquer {node.label}" disabled={!node.copyable} onclick={() => act(node.id, 'duplicate')}>
							<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2" /><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" /></svg>
						</button>
						<button tabindex="-1" class="danger" title="Supprimer" aria-label="Supprimer {node.label}" disabled={!node.removable} onclick={() => act(node.id, 'delete')}>
							<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" /></svg>
						</button>
					{/if}
				</span>
			</div>
			{#if open}
				<ul role="group">{@render branch(node.children, depth + 1)}</ul>
			{/if}
		</li>
	{/each}
	{#if depth === 1}
		<li class="end {drop?.position === 'end' ? `drop-end${drop.valid ? '' : ' drop-invalid'}` : ''}" role="none"></li>
	{/if}
{/snippet}

{#if dragging}
	<div class="ghost" class:invalid={!drop?.valid} style:left="{dragging.x + 16}px" style:top="{dragging.y + 14}px" aria-hidden="true">
		{dragging.label}
	</div>
{/if}

<style>
	.tree-panel {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
		min-height: 100%;
		user-select: none;
	}
	.tree-panel.is-dragging,
	.tree-panel.is-dragging * {
		cursor: grabbing;
	}
	.tree-head {
		display: flex;
		align-items: center;
		gap: 0.25rem;
	}
	.count {
		font-size: 0.78rem;
		color: var(--yack-gray);
	}
	.spacer {
		flex: 1;
	}
	button {
		font: inherit;
		color: inherit;
		background: none;
		border: 0;
		padding: 0;
		cursor: pointer;
	}
	button:disabled {
		cursor: default;
		opacity: 0.35;
	}
	svg {
		width: 16px;
		height: 16px;
		fill: none;
		stroke: currentColor;
		stroke-width: 1.8;
		stroke-linecap: round;
		stroke-linejoin: round;
		flex: none;
	}
	.tool {
		display: inline-grid;
		place-items: center;
		width: 28px;
		height: 28px;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
		color: var(--yack-dark);
	}
	.tool:not(:disabled):hover {
		border-color: var(--yack-magenta);
		color: var(--yack-magenta);
	}
	.notice {
		display: grid;
		grid-template-columns: auto 1fr auto;
		gap: 0.4rem;
		align-items: start;
		padding: 0.5rem 0.6rem;
		font-size: 0.78rem;
		line-height: 1.35;
		background: #fbeef6;
		color: var(--yack-magenta-dark);
		border: 1px solid #efc7df;
		border-radius: 0.375rem;
	}
	.notice button {
		font-size: 1rem;
		line-height: 1;
	}
	.empty,
	.hint {
		margin: 0;
		font-size: 0.78rem;
		color: var(--yack-gray);
	}
	.hint {
		margin-top: auto;
		padding-top: 0.5rem;
		font-size: 0.72rem;
	}
	ul {
		list-style: none;
		margin: 0;
		padding: 0;
	}
	.tree {
		display: flex;
		flex-direction: column;
		gap: 2px;
	}
	.row {
		--indent: calc(var(--depth) * 1rem);
		position: relative;
		display: flex;
		align-items: center;
		gap: 0.4rem;
		min-height: 40px;
		padding: 0.3rem 0.4rem 0.3rem calc(0.25rem + var(--indent));
		border: 1px solid transparent;
		border-radius: 0.375rem;
		color: var(--yack-dark);
		cursor: grab;
		touch-action: pan-y;
	}
	.row:hover,
	.row.hovered {
		background: #f5f5f8;
	}
	.row.selected {
		background: #fbeef6;
		border-color: var(--yack-magenta);
	}
	.row:focus-visible {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 1px;
	}
	.row.dragged {
		opacity: 0.4;
	}
	.grip {
		display: inline-grid;
		color: #b4b4bd;
		touch-action: none;
	}
	.grip svg {
		fill: currentColor;
		stroke: none;
		width: 14px;
	}
	.toggle svg {
		transition: transform 0.15s;
	}
	.toggle svg.open {
		transform: rotate(90deg);
	}
	.icon {
		position: relative;
		display: inline-grid;
		place-items: center;
		width: 26px;
		height: 26px;
		flex: none;
		color: var(--yack-magenta);
	}
	.glyph :global(svg) {
		display: block;
		width: 24px;
		height: 24px;
		fill: none;
	}
	.label {
		display: flex;
		flex-direction: column;
		min-width: 0;
		flex: 1;
		line-height: 1.2;
	}
	.label strong,
	.label small {
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}
	.label strong {
		font-size: 0.83rem;
		font-weight: 600;
	}
	.label small {
		font-size: 0.72rem;
		color: var(--yack-gray);
	}
	.lock {
		display: inline-grid;
		place-items: center;
		color: var(--yack-magenta);
	}
	.lock-badge {
		position: absolute;
		right: -6px;
		bottom: -5px;
		display: grid;
		place-items: center;
		width: 17px;
		height: 17px;
		border-radius: 50%;
		background: var(--yack-magenta);
		color: #fff;
		box-shadow: 0 0 0 2px #fff;
	}
	.lock-badge :global(svg) {
		width: 11px;
		height: 11px;
		stroke-width: 2.4;
	}
	.off-charte {
		flex: none;
		display: inline-grid;
		place-items: center;
		width: 1.25rem;
		height: 1.25rem;
		font-size: 0.8rem;
		font-weight: 700;
		line-height: 1;
		color: var(--yack-magenta-dark);
		border: 1px solid var(--yack-magenta);
		border-radius: 50%;
	}
	.row.locked .icon {
		color: var(--yack-dark);
	}
	/* Over the end of the label (hover / keyboard focus) so that the label keeps the whole width otherwise. */
	.actions {
		--actions-bg: #f5f5f8;
		position: absolute;
		top: 50%;
		right: 0.3rem;
		transform: translateY(-50%);
		display: none;
		gap: 2px;
		background: var(--actions-bg);
		box-shadow: -14px 0 10px -4px var(--actions-bg);
	}
	.row.selected .actions {
		--actions-bg: #fbeef6;
	}
	.row:hover .actions,
	.row:focus-within .actions {
		display: inline-flex;
	}
	.tree-panel.is-dragging .actions {
		display: none;
	}
	.actions button {
		display: inline-grid;
		place-items: center;
		width: 24px;
		height: 24px;
		border-radius: 0.25rem;
		color: var(--yack-gray);
	}
	.actions button:not(:disabled):hover {
		background: #fff;
		color: var(--yack-magenta);
	}
	.actions button.danger:not(:disabled):hover {
		color: #b0123b;
	}
	/* Drop indicators */
	.row.drop-before::before,
	.row.drop-after::after,
	.end.drop-end::before {
		content: '';
		position: absolute;
		left: var(--indent, 0);
		right: 0;
		height: 3px;
		border-radius: 2px;
		background: var(--yack-magenta);
		pointer-events: none;
	}
	.row.drop-before::before {
		top: -3px;
	}
	.row.drop-after::after {
		bottom: -3px;
	}
	.row.drop-inside {
		border-color: var(--yack-magenta);
		border-style: dashed;
	}
	.row.drop-invalid::before,
	.row.drop-invalid::after,
	.end.drop-invalid::before {
		background: #c9c9d1;
	}
	.end {
		position: relative;
		min-height: 24px;
	}
	.end.drop-end::before {
		top: 0;
	}
	.ghost {
		position: fixed;
		z-index: 1000;
		padding: 0.3rem 0.6rem;
		font-size: 0.8rem;
		font-weight: 600;
		color: #fff;
		background: var(--yack-magenta);
		border-radius: 0.375rem;
		box-shadow: 0 6px 18px rgb(33 35 49 / 25%);
		pointer-events: none;
		white-space: nowrap;
	}
	.ghost.invalid {
		background: var(--yack-gray);
	}
	:global(.gjs-toolbar-item.yb-tlb-lock) {
		cursor: help;
	}
</style>
