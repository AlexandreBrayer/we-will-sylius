<script lang="ts">
	// Image field of a block (D7, D9): thumbnail of the current image, « Choisir une image » (media library
	// window, #lib/editor/media/MediaLibrary.svelte), « Retirer », and the settings of the image
	// (#lib/blocks/image.ts): alternative text, framing, focal point. Picking an image writes the image and
	// the default alternative text of the library in one change (one Ctrl+Z); « Retirer » clears the image
	// and its settings. `options` of the trait: the historical visuals with their labels, plus '' when the
	// block accepts no image (otherwise « Retirer » is disabled).
	import { onDestroy, tick } from 'svelte';
	import { imageSettingNames } from '#lib/blocks/image.js';
	import { assetUrl } from '#lib/blocks/parse.js';
	import MediaLibrary from '#lib/editor/media/MediaLibrary.svelte';
	import type { MediaItem } from '#lib/editor/media/media.js';
	import { rovingKeydown } from '#lib/editor/panels/roving.js';
	import { getMediaLibrary } from '#lib/remote/builder.remote.js';
	import { traitSetting, type TraitField, type TraitFieldProps } from './registerTraits.js';

	type Choice = { id: string; label: string };

	let { trait, value: initial, component, labelId }: TraitFieldProps = $props();

	// The trait and the block of a field never change (one field per trait, mounted once).
	// svelte-ignore state_referenced_locally
	const name = trait.getName();
	const names = imageSettingNames(name);
	const read = (key: string): string => String(component.getAttributes()[key] ?? '');

	// svelte-ignore state_referenced_locally
	let value = $state(initial);
	let alt = $state(read(names.alt));
	let fit = $state(read(names.fit));
	let focus = $state(read(names.focus));
	let open = $state(false);
	let chooseButton: HTMLButtonElement | undefined = $state();
	let focusIndex = $state<Record<string, number | null>>({ fit: null, focus: null });

	export const update: TraitField['update'] = (next) => {
		value = next;
	};

	// The settings have no trait of their own: they follow the attributes of the block (Ctrl+Z, another field).
	const sync = () => {
		value = read(name);
		alt = read(names.alt);
		fit = read(names.fit);
		focus = read(names.focus);
	};
	// svelte-ignore state_referenced_locally
	component.on('change:attributes', sync);
	onDestroy(() => component.off('change:attributes', sync));

	const label = $derived(trait.getLabel());
	const options = $derived(traitSetting<Choice[]>(trait, 'options') ?? []);
	const optional = $derived(options.some((option) => option.id === ''));
	const library = getMediaLibrary('');
	const media = $derived((library.current ?? []).find((item) => item.path === value) ?? null);
	// A historical visual by its label of the registry (« Photo : salon résidentiel »), an upload by its name.
	const shownName = $derived(options.find((option) => option.id === value)?.label ?? media?.name ?? value);
	const thumbnail = $derived(media?.thumbnail ?? (value ? assetUrl(value) : ''));
	const id = $props.id();

	const FITS: Choice[] = [
		{ id: '', label: 'Par défaut' },
		{ id: 'couvrir', label: 'Couvrir' },
		{ id: 'contenir', label: 'Contenir' }
	];
	const FOCUSES: Choice[] = [
		{ id: '', label: 'Par défaut' },
		{ id: 'haut', label: 'Haut' },
		{ id: 'centre', label: 'Centre' },
		{ id: 'bas', label: 'Bas' }
	];

	function choose(item: MediaItem) {
		open = false;
		if (item.path !== value || item.alt !== alt) component.addAttributes({ [name]: item.path, [names.alt]: item.alt });
		void tick().then(() => chooseButton?.focus());
	}

	function close() {
		open = false;
		void tick().then(() => chooseButton?.focus());
	}

	function remove() {
		if (!optional || value === '') return;
		component.addAttributes({ [name]: '', [names.alt]: '', [names.fit]: '', [names.focus]: '' });
	}

	function setting(key: string, next: string) {
		if (read(key) !== next) component.addAttributes({ [key]: next });
	}
</script>

<div class="yb-image">
	<div class="yb-trait-label" id={labelId}>{label}</div>
	<div class="current" data-test-image-current={value}>
		{#if value}
			<img src={thumbnail} alt="" />
			<span class="name" title={value}>{shownName}</span>
		{:else}
			<span class="none">Aucune image</span>
		{/if}
	</div>
	<div class="actions" role="group" aria-labelledby={labelId}>
		<button type="button" class="yb-btn yb-btn-primary" bind:this={chooseButton} aria-haspopup="dialog" onclick={() => (open = true)} data-test-image-choose
			>Choisir une image</button
		>
		<button
			type="button"
			class="yb-btn yb-btn-outline"
			disabled={!optional || value === ''}
			aria-describedby={optional ? undefined : `${id}-required`}
			onclick={remove}
			data-test-image-remove>Retirer</button
		>
	</div>
	{#if !optional}<small class="yb-trait-help" id="{id}-required">Ce bloc demande une image : choisissez-en une autre pour la remplacer.</small>{/if}

	{#if value}
		<label class="alt">
			<span>Texte alternatif</span>
			<input
				type="text"
				maxlength="255"
				value={alt}
				aria-describedby="{id}-alt-help"
				onchange={(event) => setting(names.alt, event.currentTarget.value.trim())}
				data-test-image-alt
			/>
		</label>
		<small class="yb-trait-help" id="{id}-alt-help">Ce que l’image montre, pour les lecteurs d’écran. Vide : image décorative.</small>

		{#each [{ key: 'fit', title: 'Cadrage', choices: FITS, current: fit, attribute: names.fit }, { key: 'focus', title: 'Point d’intérêt', choices: FOCUSES, current: focus, attribute: names.focus }] as group (group.key)}
			{@const selectedIndex = Math.max(0, group.choices.findIndex((choice) => choice.id === group.current))}
			{@const tabStop = focusIndex[group.key] ?? selectedIndex}
			<div class="setting">
				<span class="yb-trait-label" id="{id}-{group.key}">{group.title}</span>
				<div
					class="segments"
					role="toolbar"
					aria-labelledby="{id}-{group.key}"
					tabindex="-1"
					onkeydown={(event) => {
						const next = rovingKeydown(event, tabStop);
						if (next !== null) focusIndex[group.key] = next;
					}}
					onfocusout={() => (focusIndex[group.key] = null)}
				>
					{#each group.choices as choice, index (choice.id)}
						<button
							type="button"
							data-roving
							class:selected={choice.id === group.current}
							aria-pressed={choice.id === group.current}
							tabindex={index === tabStop ? 0 : -1}
							onclick={() => setting(group.attribute, choice.id)}
							data-test-image-setting="{group.key}:{choice.id}">{choice.label}</button
						>
					{/each}
				</div>
			</div>
		{/each}
		<small class="yb-trait-help">Le point d’intérêt est la partie gardée quand l’image est rognée.</small>
	{/if}

	{#if open}
		<MediaLibrary current={value} {label} onchoose={choose} onclose={close} />
	{/if}
</div>

<style>
	.yb-image {
		display: grid;
		gap: 0.5rem;
	}
	.current {
		display: flex;
		align-items: center;
		gap: 0.6rem;
		min-height: 3.5rem;
		padding: 0.4rem;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
	}
	.current img {
		flex: none;
		width: 4.5rem;
		height: 3rem;
		object-fit: contain;
		border-radius: 0.25rem;
		background: color-mix(in srgb, var(--yack-border) 60%, transparent);
	}
	.name {
		min-width: 0;
		overflow: hidden;
		font-size: 0.8rem;
		text-overflow: ellipsis;
		white-space: nowrap;
		color: var(--yack-dark);
	}
	.none {
		font-size: 0.8rem;
		color: var(--yack-gray);
	}
	.actions {
		display: flex;
		flex-wrap: wrap;
		gap: 0.4rem;
	}
	.actions .yb-btn {
		padding: 0.4rem 0.7rem;
		font-size: 0.8rem;
	}
	.actions .yb-btn:focus-visible {
		outline: 2px solid var(--yack-dark);
		outline-offset: 2px;
	}
	.alt {
		display: grid;
		gap: 0.3rem;
		font-size: 0.8rem;
		font-weight: 600;
		color: var(--yack-dark);
	}
	.alt input {
		padding: 0.45rem 0.55rem;
		font: inherit;
		font-weight: 400;
		font-size: 0.85rem;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
		background: transparent;
	}
	.alt input:focus-visible {
		outline: 2px solid var(--yack-magenta);
		outline-offset: 1px;
	}
	.setting {
		display: grid;
		gap: 0.3rem;
	}
	.segments {
		display: flex;
		flex-wrap: wrap;
		border: 1px solid var(--yack-border);
		border-radius: 0.375rem;
		overflow: hidden;
	}
	.segments button {
		flex: 1 0 auto;
		padding: 0.4rem 0.5rem;
		font: inherit;
		font-size: 0.8rem;
		font-weight: 500;
		color: var(--yack-dark);
		background: transparent;
		border: 0;
		border-right: 1px solid var(--yack-border);
		cursor: pointer;
	}
	.segments button:last-child {
		border-right: 0;
	}
	.segments button.selected {
		background: color-mix(in srgb, var(--yack-magenta) 14%, transparent);
		color: var(--yack-magenta-dark);
		box-shadow: inset 0 -3px 0 var(--yack-magenta);
		font-weight: 600;
	}
	.segments button:focus-visible {
		outline: 2px solid var(--yack-dark);
		outline-offset: -3px;
	}
</style>
