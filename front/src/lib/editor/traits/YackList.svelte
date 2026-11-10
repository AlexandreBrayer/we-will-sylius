<script lang="ts">
	// List of a block (FAQ, documents; D9) edited as cards: one field per column (`columns` of the
	// registry, LIST_TRAITS), Ajouter, Supprimer, and moving by drag and drop, by the arrows on the
	// handle or by the buttons Monter / Descendre (announced). The stored value stays one line per
	// element, columns separated by « | » (#lib/editor/traits/list.ts): a « | » or a line break typed
	// in a field is refused with a message. Each change is one undo step (Ctrl+Z); typing is written
	// after a short pause, or when the field is left.
	import { tick } from 'svelte';
	import { documentLink } from '#lib/blocks/parse.js';
	import { traitSetting, type TraitField, type TraitFieldProps } from './registerTraits.js';
	import { ArrowDown, ArrowUp, GripVertical, Plus, Trash2 } from '#lib/editor/icons.js';
	import NoticeIcon from './NoticeIcon.svelte';
	import {
		addItem,
		isEmptyItem,
		moveItem,
		parseList,
		refusedInput,
		removeItem,
		serializeList,
		setField,
		LINE_BREAK_REFUSED,
		type ListColumn,
		type ListItem,
		type ListValue
	} from './list.js';

	/** Pause after the last key before the typed text goes to the block (one undo step per pause). */
	const TYPING_PAUSE = 700;

	let { trait, value: initial, set, labelId }: TraitFieldProps = $props();

	const columns = $derived(traitSetting<ListColumn[]>(trait, 'columns') ?? [{ label: 'Texte' }]);
	const itemLabel = $derived(traitSetting<string>(trait, 'itemLabel') ?? 'Élément');
	const addLabel = $derived(traitSetting<string>(trait, 'addLabel') ?? 'Ajouter un élément');
	const label = $derived(trait.getLabel());

	// svelte-ignore state_referenced_locally
	let list = $state<ListValue>(parseList(initial, columns.length));
	// svelte-ignore state_referenced_locally
	let written = initial;
	let errors = $state<Record<string, string>>({});
	let announcement = $state('');
	let dragFrom = $state<number | null>(null);
	let dropAt = $state<number | null>(null);
	let timer: ReturnType<typeof setTimeout> | null = null;
	let root: HTMLDivElement;

	export const update: TraitField['update'] = (next) => {
		// The echo of our own write: the cards (and a card not filled yet) stay as they are.
		if (next === written) return;
		cancelTyping();
		written = next;
		list = parseList(next, columns.length);
		errors = {};
	};

	function cancelTyping() {
		if (timer !== null) clearTimeout(timer);
		timer = null;
	}

	/** Writes the list into the block when its stored value changes (one undo step). */
	function write() {
		cancelTyping();
		const next = serializeList(list);
		if (next === written) return;
		written = next;
		set(next);
	}

	const key = (item: ListItem, column: number) => `${item.id}-${column}`;
	const fieldId = (item: ListItem, column: number) => `${labelId}-${key(item, column)}`;
	const name = (index: number) => `${itemLabel} ${index + 1}`;
	const preview = (item: ListItem) => item.fields[0]?.trim() || 'sans titre';

	function onInput(event: Event, index: number, column: number) {
		const input = event.currentTarget as HTMLInputElement | HTMLTextAreaElement;
		const item = list.items[index];
		if (!item) return;
		const previous = item.fields[column] ?? '';
		const refused = refusedInput(previous, input.value);
		if (refused) {
			const caret = Math.max(0, (input.selectionStart ?? previous.length) - (input.value.length - previous.length));
			input.value = previous;
			input.setSelectionRange(caret, caret);
			errors = { ...errors, [key(item, column)]: refused };
			return;
		}
		// Nothing changed (an input event after a refused one): the message stays.
		if (input.value === previous) return;
		if (errors[key(item, column)]) {
			const { [key(item, column)]: _, ...rest } = errors;
			errors = rest;
		}
		list = setField(list, index, column, input.value);
		cancelTyping();
		timer = setTimeout(write, TYPING_PAUSE);
	}

	function onKeydown(event: KeyboardEvent, item: ListItem, column: number) {
		if (event.key !== 'Enter') return;
		// Enter writes the field; it never breaks the line (the element is one line of the list).
		event.preventDefault();
		if (columns[column]?.multiline) errors = { ...errors, [key(item, column)]: LINE_BREAK_REFUSED };
		write();
	}

	async function focusIn(id: number, selector: string) {
		await tick();
		root?.querySelector(`[data-item="${id}"]`)?.querySelector<HTMLElement>(selector)?.focus();
	}

	function move(from: number, to: number, focus: string | null) {
		const item = list.items[from];
		if (!item) return;
		const next = moveItem(list, from, to);
		if (next === list) return;
		write();
		list = next;
		write();
		const position = next.items.indexOf(item) + 1;
		announcement = `« ${preview(item)} » est maintenant en position ${position} sur ${next.items.length}.`;
		if (focus) void focusIn(item.id, focus);
	}

	function add() {
		write();
		list = addItem(list, columns.length);
		const item = list.items[list.items.length - 1];
		announcement = `Nouvel élément en position ${list.items.length} : il est enregistré dès qu'un champ est rempli.`;
		void focusIn(item.id, 'input, textarea');
	}

	function remove(index: number) {
		const item = list.items[index];
		if (!item) return;
		write();
		list = removeItem(list, index);
		write();
		announcement = `Élément « ${preview(item)} » supprimé. Ctrl+Z le rétablit.`;
		const next = list.items[Math.min(index, list.items.length - 1)];
		if (next) void focusIn(next.id, '[data-action="remove"]');
		else void tick().then(() => root?.querySelector<HTMLElement>('[data-action="add"]')?.focus());
	}

	function onHandleKeydown(event: KeyboardEvent, index: number) {
		const to = { ArrowUp: index - 1, ArrowDown: index + 1, Home: 0, End: list.items.length - 1 }[event.key];
		if (to === undefined) return;
		event.preventDefault();
		move(index, to, '[data-action="handle"]');
	}

	/** Notes of an element: what the shop will not show. */
	function notes(item: ListItem): string[] {
		if (item.raw === null && isEmptyItem(item)) return ['Pas encore enregistré : remplissez un champ.'];
		const out: string[] = [];
		columns.forEach((column, c) => {
			const value = item.fields[c]?.trim() ?? '';
			if (column.required && value === '') out.push(`« ${column.label} » est vide : cet élément n'est pas affiché sur le site.`);
			else if (column.link && value !== '' && documentLink(value) === '')
				out.push(`Lien non reconnu : la page ne peut pas être enregistrée avec ce lien (exemples : /sav, page:nos-services, https://…).`);
		});
		return out;
	}

	// Drag and drop: the handle starts it, a card is a drop target (before or after its middle).
	function onDragStart(event: DragEvent, index: number) {
		dragFrom = index;
		event.dataTransfer?.setData('application/x-yack-list', String(index));
		if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
		const card = (event.currentTarget as HTMLElement).closest('li');
		if (card && event.dataTransfer) event.dataTransfer.setDragImage(card, 16, 16);
	}

	function onDragOver(event: DragEvent, index: number) {
		if (dragFrom === null) return;
		event.preventDefault();
		if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
		const box = (event.currentTarget as HTMLElement).getBoundingClientRect();
		dropAt = event.clientY < box.top + box.height / 2 ? index : index + 1;
	}

	function onDrop(event: DragEvent) {
		if (dragFrom === null || dropAt === null) return;
		event.preventDefault();
		const from = dragFrom;
		const to = dropAt > from ? dropAt - 1 : dropAt;
		dragFrom = null;
		dropAt = null;
		move(from, to, '[data-action="handle"]');
	}

	function onDragEnd() {
		dragFrom = null;
		dropAt = null;
	}
</script>

<div class="yb-list ui-stack" role="group" aria-labelledby={labelId} bind:this={root}>
	<div class="ui-label" id={labelId}>{label}<span class="count">&nbsp;({list.items.length})</span></div>

	{#if list.items.length === 0}
		<p class="ui-help">Aucun élément pour l'instant.</p>
	{:else}
		<ol class="cards">
			{#each list.items as item, index (item.id)}
				{@const itemNotes = notes(item)}
				<li
					class="card"
					class:dragging={dragFrom === index}
					class:drop-before={dropAt === index && dragFrom !== null && dragFrom !== index && dragFrom !== index - 1}
					class:drop-after={dropAt === index + 1 && index === list.items.length - 1 && dragFrom !== null && dragFrom !== index}
					data-item={item.id}
					aria-labelledby="{labelId}-{item.id}-title"
					ondragover={(event) => onDragOver(event, index)}
					ondrop={onDrop}
				>
					<!-- The whole head is dragged (a dragged <button> alone does not start in every browser). -->
					<div class="head" role="presentation" draggable="true" ondragstart={(event) => onDragStart(event, index)} ondragend={onDragEnd}>
						<button
							type="button"
							class="ui-icon-btn is-quiet is-small handle"
							data-action="handle"
							aria-label="Déplacer {name(index).toLowerCase()} : {preview(item)}. Flèches haut et bas pour changer sa place."
							title="Glisser pour déplacer, ou flèches haut et bas"
							onkeydown={(event) => onHandleKeydown(event, index)}
						>
							<GripVertical class="ui-ic ui-ic-sm" />
						</button>
						<span class="title" id="{labelId}-{item.id}-title">{name(index)}</span>
						<span class="actions">
							<button
								type="button"
								class="ui-icon-btn is-quiet is-small"
								data-action="up"
								aria-disabled={index === 0}
								aria-label="Monter {name(index).toLowerCase()}"
								title="Monter"
								onclick={() => index > 0 && move(index, index - 1, '[data-action="up"]')}><ArrowUp class="ui-ic ui-ic-sm" /></button
							>
							<button
								type="button"
								class="ui-icon-btn is-quiet is-small"
								data-action="down"
								aria-disabled={index === list.items.length - 1}
								aria-label="Descendre {name(index).toLowerCase()}"
								title="Descendre"
								onclick={() => index < list.items.length - 1 && move(index, index + 1, '[data-action="down"]')}><ArrowDown class="ui-ic ui-ic-sm" /></button
							>
							<button
								type="button"
								class="ui-icon-btn is-quiet is-small"
								data-action="remove"
								aria-label="Supprimer {name(index).toLowerCase()}"
								title="Supprimer"
								onclick={() => remove(index)}><Trash2 class="ui-ic ui-ic-sm" /></button
							>
						</span>
					</div>

					{#each columns as column, c (c)}
						{@const error = errors[key(item, c)]}
						<label class="ui-label" for={fieldId(item, c)}>{column.label}</label>
						{#if column.multiline}
							<textarea
								class="ui-input"
								id={fieldId(item, c)}
								rows="3"
								value={item.fields[c] ?? ''}
								placeholder={column.placeholder ?? ''}
								aria-invalid={error ? 'true' : undefined}
								aria-describedby={error ? `${fieldId(item, c)}-error` : undefined}
								oninput={(event) => onInput(event, index, c)}
								onkeydown={(event) => onKeydown(event, item, c)}
								onblur={write}
							></textarea>
						{:else}
							<input
								class="ui-input"
								id={fieldId(item, c)}
								type="text"
								value={item.fields[c] ?? ''}
								placeholder={column.placeholder ?? ''}
								aria-invalid={error ? 'true' : undefined}
								aria-describedby={error ? `${fieldId(item, c)}-error` : undefined}
								oninput={(event) => onInput(event, index, c)}
								onkeydown={(event) => onKeydown(event, item, c)}
								onblur={write}
							/>
						{/if}
						{#if error}<small class="ui-error" id="{fieldId(item, c)}-error" role="alert">{error}</small>{/if}
					{/each}
					{#each itemNotes as note (note)}<p class="ui-note"><NoticeIcon /><span>{note}</span></p>{/each}
				</li>
			{/each}
		</ol>
	{/if}

	<button type="button" class="ui-btn ui-btn-dashed add" data-action="add" onclick={add}><Plus class="ui-ic ui-ic-sm" />{addLabel}</button>
	<p class="ui-sr-only" role="status" aria-live="polite">{announcement}</p>
</div>

<style>
	.count {
		color: var(--ui-text-label);
	}
	.cards {
		display: grid;
		gap: var(--ui-space-2);
		margin: 0;
		padding: 0;
		list-style: none;
	}
	.card {
		position: relative;
		display: grid;
		gap: var(--ui-space-1);
		padding: var(--ui-space-1) var(--ui-space-2) var(--ui-space-2);
		border: var(--ui-border-width) solid var(--ui-border);
		border-radius: var(--ui-radius);
	}
	.card.dragging {
		opacity: 0.5;
	}
	/* Drop mark: a line of the accent between two cards (it marks, it does not fill a control). */
	.card.drop-before::before,
	.card.drop-after::after {
		content: '';
		position: absolute;
		left: 0;
		right: 0;
		height: var(--ui-focus-width);
		background: var(--ui-accent);
	}
	.card.drop-before::before {
		top: calc(-1 * var(--ui-space-1) - var(--ui-border-width));
	}
	.card.drop-after::after {
		bottom: calc(-1 * var(--ui-space-1) - var(--ui-border-width));
	}
	.head {
		display: flex;
		align-items: center;
		gap: var(--ui-space-1);
		min-height: var(--ui-control);
		cursor: grab;
	}
	.title {
		flex: 1;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		font-size: var(--ui-text-sm);
		font-weight: var(--ui-weight-bold);
		color: var(--ui-text);
	}
	.actions {
		display: flex;
		gap: var(--ui-border-width);
	}
	.handle {
		cursor: grab;
	}
	.add {
		justify-self: start;
	}
</style>
