<script lang="ts">
	// Link of a block (D9): shows the readable label of the stored link (« Page : Nos services »), and a
	// search (combobox) among the site shortcuts, the CMS pages, the categories and the products; an
	// address typed instead (https://…, mailto:…, tel:…, /chemin) is checked like resolveLink() of the
	// shop. The stored value keeps its format (page:<slug>, /sav, https://…): it changes only when the
	// editor picks or types another link, through `set()` (one undo step).
	import { getLinkLabel, searchLinks } from '#lib/remote/builder.remote.js';
	import { checkLinkInput, describeLink, looksLikeAddress, matchShortcuts, type LinkDescription, type LinkOption } from './link.js';
	import type { TraitField, TraitFieldProps } from './registerTraits.js';
	import NoticeIcon from './NoticeIcon.svelte';

	let { trait, value: initial, set, labelId }: TraitFieldProps = $props();

	// svelte-ignore state_referenced_locally
	let value = $state(initial);
	let q = $state('');
	let open = $state(false);
	let found = $state<LinkOption[]>([]);
	let loading = $state(false);
	let failed = $state(false);
	let active = $state(-1);
	let error = $state<string | null>(null);
	let announce = $state('');
	let input = $state<HTMLInputElement>();
	/** Focus given back by « Retirer »: the list stays closed, so « Lien retiré » is the last announce. */
	let quietFocus = false;
	/** Labels of the links picked here, shown before the server confirms them. */
	const picked = new Map<string, LinkDescription>();
	// svelte-ignore state_referenced_locally
	let described = $state<LinkDescription>(describeLink(initial, null));

	export const update: TraitField['update'] = (next) => {
		value = next;
		error = null;
	};

	const uid = $props.id();
	const label = $derived(trait.getLabel());

	// Label of the stored value: at once from what is known here, then from Sylius (titles, names).
	$effect(() => {
		const current = value;
		described = picked.get(current) ?? describeLink(current, null);
		if (current.trim() === '') return;
		let cancelled = false;
		getLinkLabel(current).then(
			(description) => {
				if (!cancelled) described = description;
			},
			() => {}
		);
		return () => {
			cancelled = true;
		};
	});

	const address = $derived(looksLikeAddress(q));
	const check = $derived(address ? checkLinkInput(q) : null);
	const options = $derived.by((): LinkOption[] => {
		if (address) {
			return check?.ok ? [{ value: check.value, label: check.value, kind: 'address', detail: describeLink(check.value, null).label }] : [];
		}
		return [...matchShortcuts(q), ...found];
	});

	const GROUPS: { kind: LinkOption['kind']; title: string }[] = [
		{ kind: 'address', title: 'Adresse saisie' },
		{ kind: 'shortcut', title: 'Raccourcis du site' },
		{ kind: 'page', title: 'Pages' },
		{ kind: 'category', title: 'Catégories' },
		{ kind: 'product', title: 'Produits' }
	];
	const groups = $derived(
		GROUPS.map((group) => ({ ...group, items: options.flatMap((option, index) => (option.kind === group.kind ? [{ option, index }] : [])) })).filter(
			(group) => group.items.length > 0
		)
	);

	// Debounced search of Sylius (pages, categories, products); not for a typed address.
	$effect(() => {
		const term = q.trim();
		if (!open || looksLikeAddress(term)) {
			loading = false;
			return;
		}
		let cancelled = false;
		const timer = setTimeout(async () => {
			loading = true;
			failed = false;
			try {
				const results = await searchLinks(term);
				if (!cancelled) found = results;
			} catch {
				if (!cancelled) {
					found = [];
					failed = true;
				}
			} finally {
				if (!cancelled) loading = false;
			}
		}, 250);
		return () => {
			cancelled = true;
			clearTimeout(timer);
		};
	});

	$effect(() => {
		if (!open) return;
		announce = loading ? '' : options.length === 0 ? 'Aucun résultat' : options.length === 1 ? '1 résultat' : `${options.length} résultats`;
	});

	function description(option: LinkOption): LinkDescription {
		const prefix = { shortcut: 'Site', page: 'Page', category: 'Catégorie', product: 'Produit' } as const;
		if (option.kind === 'address') return describeLink(option.value, null);
		return { kind: option.kind, label: `${prefix[option.kind]} : ${option.label}`, missing: false };
	}

	function close() {
		open = false;
		active = -1;
	}

	function pick(option: LinkOption) {
		const chosen = description(option);
		picked.set(option.value, chosen);
		q = '';
		error = null;
		close();
		announce = `Lien choisi : ${chosen.label}`;
		if (option.value === value) return;
		value = option.value;
		described = chosen;
		set(option.value);
	}

	function remove() {
		error = null;
		value = '';
		set('');
		announce = 'Lien retiré';
		quietFocus = true;
		input?.focus();
		quietFocus = false;
	}

	function keydown(event: KeyboardEvent) {
		const count = options.length;
		switch (event.key) {
			case 'ArrowDown':
			case 'ArrowUp': {
				event.preventDefault();
				if (!open) open = true;
				if (count === 0) return;
				const step = event.key === 'ArrowDown' ? 1 : -1;
				active = active === -1 ? (step === 1 ? 0 : count - 1) : (active + step + count) % count;
				document.getElementById(`${uid}-option-${active}`)?.scrollIntoView({ block: 'nearest' });
				return;
			}
			case 'Enter': {
				event.preventDefault();
				const option = options[active] ?? (address ? options[0] : undefined);
				if (option) pick(option);
				else if (check && !check.ok) error = check.message;
				return;
			}
			case 'Escape':
				if (open || q !== '') {
					event.preventDefault();
					event.stopPropagation();
					q = '';
					error = null;
					close();
				}
				return;
		}
	}
</script>

<div class="yb-link">
	<div class="yb-trait-label" id={labelId}>{label}</div>

	<div class="current" class:missing={described.missing} class:empty={described.kind === 'empty'}>
		{#if described.missing}<NoticeIcon />{/if}
		<span id="{uid}-current" title={value === '' ? undefined : value} data-test-link-label>{described.label}</span>
		{#if value !== ''}
			<button type="button" class="remove" onclick={remove} aria-label="Retirer le lien ({label})">Retirer</button>
		{/if}
	</div>

	<input
		bind:this={input}
		id="{uid}-q"
		type="text"
		role="combobox"
		autocomplete="off"
		spellcheck="false"
		maxlength="2000"
		placeholder="Chercher une page, une catégorie, un produit, ou saisir une adresse"
		aria-labelledby={labelId}
		aria-describedby="{uid}-current {uid}-help"
		aria-autocomplete="list"
		aria-expanded={open}
		aria-controls="{uid}-list"
		aria-activedescendant={open && active >= 0 ? `${uid}-option-${active}` : undefined}
		aria-invalid={error ? 'true' : undefined}
		bind:value={q}
		oninput={() => {
			open = true;
			active = -1;
			error = null;
		}}
		onfocus={() => {
			if (!quietFocus) open = true;
		}}
		onblur={close}
		onkeydown={keydown}
		data-test-link-search
	/>
	<small id="{uid}-help" class="yb-trait-help">Ou une adresse : https://…, mailto:…, tel:…, ou /chemin du site.</small>
	{#if error}<small class="yb-trait-error" role="alert">{error}</small>{/if}

	<!-- The focus stays in the search (aria-activedescendant): tabindex -1, a scrolling list being a tab stop in Chrome,
	     and no blur when the list (options, scroll bar) is pressed. -->
	<div class="list" id="{uid}-list" role="listbox" aria-labelledby={labelId} tabindex="-1" hidden={!open} onmousedown={(event) => event.preventDefault()}>
		{#each groups as group (group.kind)}
			<div role="group" aria-labelledby="{uid}-group-{group.kind}">
				<div class="group" id="{uid}-group-{group.kind}" role="presentation">{group.title}</div>
				{#each group.items as { option, index } (option.kind + option.value)}
					<div
						id="{uid}-option-{index}"
						role="option"
						class="option"
						class:active={index === active}
						aria-selected={index === active}
						tabindex="-1"
						onclick={() => pick(option)}
						onkeydown={() => {}}
						data-test-link-option={option.value}
					>
						<span>{option.kind === 'address' ? `Utiliser ${option.label}` : option.label}</span>
						{#if option.detail}<small>{option.detail}</small>{/if}
					</div>
				{/each}
			</div>
		{/each}
		{#if address && check && !check.ok}
			<p class="hint">{check.message}</p>
		{:else if options.length === 0}
			<p class="hint">{loading ? 'Recherche…' : failed ? 'Recherche indisponible : réessayez.' : 'Aucun résultat'}</p>
		{:else if loading}
			<p class="hint">Recherche…</p>
		{/if}
	</div>
	<span class="sr-only" aria-live="polite">{announce}</span>
</div>

<style>
	.yb-link {
		display: grid;
		gap: 0.4rem;
		/* GrapesJS sets its traits in font-weight: lighter. */
		font-weight: 400;
	}
	.current {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		min-height: 1.8rem;
		padding: 0.3rem 0.5rem;
		font-size: 0.82rem;
		font-weight: 600;
		color: var(--yack-dark);
		border: 1px solid var(--yack-border);
		background: transparent;
		border-radius: 0.25rem;
	}
	.current span {
		flex: 1;
		min-width: 0;
		overflow-wrap: anywhere;
	}
	.current.empty {
		font-weight: 400;
		color: var(--yack-gray);
		border-style: dashed;
	}
	.current.missing {
		color: var(--yack-magenta-dark);
	}
	.remove {
		flex: none;
		padding: 0.15rem 0.45rem;
		font: inherit;
		font-size: 0.75rem;
		font-weight: 500;
		color: var(--yack-magenta-dark);
		background: transparent;
		border: 1px solid var(--yack-border);
		border-radius: 0.25rem;
		cursor: pointer;
	}
	.remove:focus-visible,
	input:focus-visible {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 1px;
	}
	input {
		width: 100%;
		box-sizing: border-box;
		padding: 0.45rem 0.55rem;
		font: inherit;
		font-size: 0.82rem;
		color: var(--yack-dark);
		background: transparent;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
	}
	.list {
		max-height: 280px;
		overflow-y: auto;
		padding: 0.25rem;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
	}
	.list[hidden] {
		display: none;
	}
	.group {
		padding: 0.35rem 0.4rem 0.15rem;
		font-size: 0.7rem;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.03em;
		color: var(--yack-gray);
	}
	.option {
		display: flex;
		flex-direction: column;
		padding: 0.35rem 0.45rem;
		font-size: 0.82rem;
		color: var(--yack-dark);
		border-radius: 0.25rem;
		cursor: pointer;
		overflow-wrap: anywhere;
	}
	.option:hover,
	.option.active {
		background: color-mix(in srgb, var(--yack-magenta) 12%, transparent);
	}
	.option small {
		font-size: 0.72rem;
		color: var(--yack-gray);
	}
	.hint {
		margin: 0;
		padding: 0.35rem 0.45rem;
		font-size: 0.78rem;
		color: var(--yack-gray);
	}
	.sr-only {
		position: absolute;
		width: 1px;
		height: 1px;
		overflow: hidden;
		clip-path: inset(50%);
		white-space: nowrap;
	}
</style>
