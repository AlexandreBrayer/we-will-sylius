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

<div class="yb-list" role="group" aria-labelledby={labelId} bind:this={root}>
	<div class="yb-trait-label" id={labelId}>{label}<span class="count">&nbsp;({list.items.length})</span></div>

	{#if list.items.length === 0}
		<p class="empty">Aucun élément pour l'instant.</p>
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
							class="handle"
							data-action="handle"
							aria-label="Déplacer {name(index).toLowerCase()} : {preview(item)}. Flèches haut et bas pour changer sa place."
							title="Glisser pour déplacer, ou flèches haut et bas"
							onkeydown={(event) => onHandleKeydown(event, index)}
						>
							<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01" /></svg>
						</button>
						<span class="title" id="{labelId}-{item.id}-title">{name(index)}</span>
						<span class="actions">
							<button
								type="button"
								data-action="up"
								aria-disabled={index === 0}
								aria-label="Monter {name(index).toLowerCase()}"
								title="Monter"
								onclick={() => index > 0 && move(index, index - 1, '[data-action="up"]')}>↑</button
							>
							<button
								type="button"
								data-action="down"
								aria-disabled={index === list.items.length - 1}
								aria-label="Descendre {name(index).toLowerCase()}"
								title="Descendre"
								onclick={() => index < list.items.length - 1 && move(index, index + 1, '[data-action="down"]')}>↓</button
							>
							<button
								type="button"
								class="remove"
								data-action="remove"
								aria-label="Supprimer {name(index).toLowerCase()}"
								title="Supprimer"
								onclick={() => remove(index)}>Supprimer</button
							>
						</span>
					</div>

					{#each columns as column, c (c)}
						{@const error = errors[key(item, c)]}
						<label class="field" for={fieldId(item, c)}>{column.label}</label>
						{#if column.multiline}
							<textarea
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
						{#if error}<small class="yb-trait-error" id="{fieldId(item, c)}-error" role="alert">{error}</small>{/if}
					{/each}
					{#each itemNotes as note (note)}<p class="note"><NoticeIcon /><span>{note}</span></p>{/each}
				</li>
			{/each}
		</ol>
	{/if}

	<button type="button" class="add" data-action="add" onclick={add}>+ {addLabel}</button>
	<p class="visually-hidden" role="status" aria-live="polite">{announcement}</p>
</div>

<style>
	.yb-list {
		display: grid;
		gap: 0.5rem;
		/* GrapesJS gives its field wrapper a thin weight (100). */
		font-weight: 400;
	}
	.count {
		font-weight: 400;
		color: var(--yack-gray);
	}
	.empty {
		margin: 0;
		font-size: 0.8rem;
		color: var(--yack-gray);
	}
	.cards {
		display: grid;
		gap: 0.5rem;
		margin: 0;
		padding: 0;
		list-style: none;
	}
	.card {
		position: relative;
		display: grid;
		gap: 0.3rem;
		padding: 0.5rem 0.6rem 0.6rem;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
	}
	.card.dragging {
		opacity: 0.5;
	}
	.card.drop-before::before,
	.card.drop-after::after {
		content: '';
		position: absolute;
		left: 0;
		right: 0;
		height: 3px;
		border-radius: 2px;
		background: var(--yack-magenta);
	}
	.card.drop-before::before {
		top: -0.35rem;
	}
	.card.drop-after::after {
		bottom: -0.35rem;
	}
	.head {
		cursor: grab;
		display: flex;
		align-items: center;
		gap: 0.35rem;
	}
	.title {
		flex: 1;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		font-size: 0.78rem;
		font-weight: 600;
		color: var(--yack-dark);
	}
	.actions {
		display: flex;
		gap: 0.2rem;
	}
	.head button {
		padding: 0.15rem 0.4rem;
		font: inherit;
		font-size: 0.75rem;
		color: var(--yack-dark);
		background: transparent;
		border: 1px solid var(--yack-border);
		border-radius: 0.3rem;
		cursor: pointer;
	}
	.head button[aria-disabled='true'] {
		color: var(--yack-gray);
		opacity: 0.45;
		cursor: default;
	}
	.head .handle {
		display: inline-flex;
		padding: 0.15rem;
		border-color: transparent;
		cursor: grab;
	}
	.handle svg {
		width: 1rem;
		height: 1rem;
		fill: none;
		stroke: currentColor;
		stroke-width: 3;
		stroke-linecap: round;
	}
	.head .remove {
		color: var(--yack-magenta-dark);
	}
	.head button:focus-visible,
	.add:focus-visible {
		outline: 2px solid var(--yack-dark);
		outline-offset: 1px;
	}
	.field {
		font-size: 0.75rem;
		font-weight: 500;
		color: var(--yack-gray);
	}
	input,
	textarea {
		width: 100%;
		box-sizing: border-box;
		padding: 0.4rem 0.5rem;
		font: inherit;
		font-size: 0.85rem;
		color: var(--yack-dark);
		background: transparent;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
		resize: vertical;
	}
	input:focus-visible,
	textarea:focus-visible {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 1px;
	}
	input[aria-invalid='true'],
	textarea[aria-invalid='true'] {
		border-color: var(--yack-magenta-dark);
	}
	.note {
		display: flex;
		align-items: flex-start;
		gap: 0.4rem;
		margin: 0.2rem 0 0;
		padding: 0.3rem 0.5rem;
		font-size: 0.75rem;
		border-radius: 0.25rem;
		/* Neutral notice: the text and the mark carry it, not a colour. */
		background: color-mix(in srgb, var(--yack-dark) 5%, transparent);
		color: var(--yack-dark);
	}
	.add {
		justify-self: start;
		padding: 0.35rem 0.7rem;
		font: inherit;
		font-size: 0.8rem;
		font-weight: 600;
		color: var(--yack-magenta-dark);
		background: transparent;
		border: 1px dashed var(--yack-magenta);
		border-radius: 0.375rem;
		cursor: pointer;
	}
	.visually-hidden {
		position: absolute;
		width: 1px;
		height: 1px;
		overflow: hidden;
		clip-path: inset(50%);
		white-space: nowrap;
	}
</style>
