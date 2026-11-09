<script lang="ts">
	// Field of a search form with suggestions (ARIA 1.2 combobox with a listbox): from 2 characters, 200 ms
	// after the last keystroke, up to 6 products (thumbnail, reference, name) and 3 categories, then "Voir tous
	// les résultats". Keyboard: arrows move in the list, Enter opens the active option (or submits the form),
	// Escape closes; a click outside closes. Without JavaScript the field stays a plain input of its GET form.
	// Header: whole catalogue, form to /recherche. "Rechercher dans la liste" of a category page: `category`
	// limits the suggestions to it, `allHref` leads to the list filtered by the text. Logic: #lib/search/suggest.ts.
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { getSearchSuggestions } from '#lib/remote/catalog.remote.js';
	import {
		debounce,
		noSuggestions,
		moveActive,
		optionUrls,
		searchHref,
		shouldSuggest,
		SUGGEST_DELAY_MS,
		type SearchSuggestions
	} from '#lib/search/suggest.js';
	import Icon from './Icon.svelte';

	let {
		value = '',
		input = $bindable(),
		category = null,
		allHref = searchHref,
		label = 'Rechercher',
		placeholder = 'Rechercher un produit, une référence…',
		size = ''
	}: {
		/** Text shown on arrival (the query of the search page, of the list). */
		value?: string;
		/** The field, for the focus given by the header. */
		input?: HTMLInputElement;
		/** Code of the category the suggestions stay in (list of a category page). */
		category?: string | null;
		/** "Voir tous les résultats": the search page, or the list filtered by the text. */
		allHref?: (text: string) => string;
		label?: string;
		placeholder?: string;
		/** Bootstrap size of the field. */
		size?: '' | 'sm';
	} = $props();

	const id = $props.id();
	const listId = `${id}-list`;
	const optionId = (index: number) => `${id}-option-${index}`;

	let text = $derived(value);
	let suggestions = $state<SearchSuggestions>(noSuggestions());
	let open = $state(false);
	let active = $state(-1);
	let list = $state<HTMLElement>();
	/** Number of the last request: an answer to an older one is ignored. */
	let request = 0;

	const urls = $derived(optionUrls(suggestions, allHref));
	const count = $derived(suggestions.products.length + suggestions.categories.length);

	function close() {
		open = false;
		active = -1;
	}

	const ask = debounce(async (query: string) => {
		const ticket = ++request;
		try {
			const found = await getSearchSuggestions({ text: query, category });
			if (ticket !== request) return;
			suggestions = found;
			active = -1;
			open = document.activeElement === input;
		} catch {
			// No suggestion is not an error: the form still leads to the results page.
			if (ticket === request) close();
		}
	}, SUGGEST_DELAY_MS);

	function onInput() {
		if (shouldSuggest(text)) {
			ask.call(text.trim());
			return;
		}
		ask.cancel();
		request++;
		suggestions = noSuggestions();
		close();
	}

	function onFocus() {
		if (shouldSuggest(text) && suggestions.query === text.trim() && suggestions.query !== '') open = true;
	}

	function onKeydown(event: KeyboardEvent) {
		if (event.key === 'Escape') {
			if (open) {
				event.preventDefault();
				close();
			}
			return;
		}
		if (event.key === 'Enter') {
			if (open && active >= 0) {
				event.preventDefault();
				const url = urls[active];
				close();
				goto(url);
			}
			return;
		}
		if (!open) {
			if (event.key === 'ArrowDown' && suggestions.query !== '' && suggestions.query === text.trim()) {
				event.preventDefault();
				open = true;
			}
			return;
		}
		const next = moveActive(active, urls.length, event.key);
		if (next === null) return;
		event.preventDefault();
		active = next;
		document.getElementById(optionId(next))?.scrollIntoView({ block: 'nearest' });
	}

	function onWindowClick(event: MouseEvent) {
		const target = event.target as Node;
		if (open && target !== input && !list?.contains(target)) close();
	}

	// Another page: the list closes.
	$effect(() => {
		void page.url;
		close();
	});
</script>

<svelte:window onclick={onWindowClick} />

<input
	bind:this={input}
	bind:value={text}
	class="form-control"
	class:form-control-sm={size === 'sm'}
	type="search"
	name="q"
	{placeholder}
	aria-label={label}
	autocomplete="off"
	role="combobox"
	aria-autocomplete="list"
	aria-expanded={open}
	aria-controls={listId}
	aria-activedescendant={open && active >= 0 ? optionId(active) : undefined}
	oninput={onInput}
	onfocus={onFocus}
	onblur={close}
	onkeydown={onKeydown}
	data-test-search-input={category === null ? '' : undefined}
	data-test-listing-search-input={category === null ? undefined : ''}
/>
<!-- The list keeps the focus in the field (mousedown), so a click follows the link before the field loses it. -->
<div
	bind:this={list}
	id={listId}
	class="yack-suggest"
	role="listbox"
	aria-label="Suggestions de recherche"
	tabindex="-1"
	hidden={!open}
	onmousedown={(event) => event.preventDefault()}
	data-test-search-suggestions
>
	{#if suggestions.products.length > 0}
		<div class="yack-suggest__group" role="group" aria-label="Produits">
			<div class="yack-suggest__title" aria-hidden="true">Produits</div>
			{#each suggestions.products as product, i (product.code)}
				<a
					id={optionId(i)}
					class="yack-suggest__option"
					class:is-active={active === i}
					role="option"
					aria-selected={active === i}
					tabindex="-1"
					href={product.url}
				>
					<span class="yack-suggest__thumb">
						{#if product.image}<img src={product.image} alt="" width="40" height="40" loading="lazy" />{:else}<Icon name="part" />{/if}
					</span>
					<span class="yack-suggest__text">
						<span class="yack-suggest__ref">{product.code}{#if product.brand}<span class="yack-suggest__brand"> · {product.brand}</span>{/if}</span>
						<span class="yack-suggest__name">{product.name}</span>
					</span>
				</a>
			{/each}
		</div>
	{/if}
	{#if suggestions.categories.length > 0}
		{@const offset = suggestions.products.length}
		<div class="yack-suggest__group" role="group" aria-label="Catégories">
			<div class="yack-suggest__title" aria-hidden="true">Catégories</div>
			{#each suggestions.categories as category, i (category.code)}
				<a
					id={optionId(offset + i)}
					class="yack-suggest__option"
					class:is-active={active === offset + i}
					role="option"
					aria-selected={active === offset + i}
					tabindex="-1"
					href={category.url}
				>
					<span class="yack-suggest__thumb"><Icon name="grid" /></span>
					<span class="yack-suggest__name">{category.name}</span>
				</a>
			{/each}
		</div>
	{/if}
	{#if count === 0}
		<p class="yack-suggest__empty">Aucune suggestion pour « {suggestions.query} ».</p>
	{/if}
	<a
		id={optionId(count)}
		class="yack-suggest__option yack-suggest__all"
		class:is-active={active === count}
		role="option"
		aria-selected={active === count}
		tabindex="-1"
		href={allHref(suggestions.query)}
	>
		Voir tous les résultats pour « {suggestions.query} »
	</a>
</div>
<span class="visually-hidden" role="status" aria-live="polite">
	{#if open}{count === 0 ? 'Aucune suggestion' : `${count} suggestion${count > 1 ? 's' : ''}`}{/if}
</span>
