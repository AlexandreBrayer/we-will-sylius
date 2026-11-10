<script lang="ts">
	// Image field of a block (D7, D9): thumbnail of the current image, « Choisir une image » (media library
	// window, #lib/editor/media/MediaLibrary.svelte), « Retirer », and the settings of the image
	// (#lib/blocks/image.ts): alternative text, framing, focal point. Picking an image writes the image and
	// the default alternative text of the library in one change (one Ctrl+Z); « Retirer » clears the image
	// and its settings. `options` of the trait: the historical visuals with their labels, plus '' when the
	// block accepts no image (otherwise « Retirer » is disabled).
	import { onDestroy, tick } from 'svelte';
	import { cleanImageAlt, IMAGE_ALT_MAX, imageSettingNames } from '#lib/blocks/image.js';
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
		const itemAlt = cleanImageAlt(item.alt);
		if (item.path !== value || itemAlt !== alt) component.addAttributes({ [name]: item.path, [names.alt]: itemAlt });
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

<div class="yb-image ui-stack">
	<div class="ui-label" id={labelId}>{label}</div>
	<div class="current" data-test-image-current={value}>
		{#if value}
			<img src={thumbnail} alt="" />
			<span class="name" title={value}>{shownName}</span>
		{:else}
			<span class="none">Aucune image</span>
		{/if}
	</div>
	<div class="ui-actions" role="group" aria-labelledby={labelId}>
		<button type="button" class="ui-btn" bind:this={chooseButton} aria-haspopup="dialog" onclick={() => (open = true)} data-test-image-choose>Choisir une image</button>
		<button
			type="button"
			class="ui-btn"
			disabled={!optional || value === ''}
			aria-describedby={optional ? undefined : `${id}-required`}
			onclick={remove}
			data-test-image-remove>Retirer</button
		>
	</div>
	{#if !optional}<small class="ui-help" id="{id}-required">Ce bloc demande une image : choisissez-en une autre pour la remplacer.</small>{/if}

	{#if value}
		<label class="ui-stack">
			<span class="ui-label">Texte alternatif</span>
			<input
				class="ui-input"
				type="text"
				maxlength={IMAGE_ALT_MAX}
				value={alt}
				aria-describedby="{id}-alt-help"
				onchange={(event) => {
					// Sylius refuses `<`, `>` and line breaks (BlockType::isImageAlt()): the field shows what is kept.
					const next = cleanImageAlt(event.currentTarget.value);
					event.currentTarget.value = next;
					setting(names.alt, next);
				}}
				data-test-image-alt
			/>
		</label>
		<small class="ui-help" id="{id}-alt-help">Ce que l’image montre, pour les lecteurs d’écran (sans &lt; ni &gt;). Vide : image décorative.</small>

		{#each [{ key: 'fit', title: 'Cadrage', choices: FITS, current: fit, attribute: names.fit }, { key: 'focus', title: 'Point d’intérêt', choices: FOCUSES, current: focus, attribute: names.focus }] as group (group.key)}
			{@const selectedIndex = Math.max(0, group.choices.findIndex((choice) => choice.id === group.current))}
			{@const tabStop = focusIndex[group.key] ?? selectedIndex}
			<div class="ui-row">
				<span class="ui-label-cell"><span class="ui-label" id="{id}-{group.key}">{group.title}</span></span>
				<div
					class="ui-control ui-seg"
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
							aria-pressed={choice.id === group.current}
							tabindex={index === tabStop ? 0 : -1}
							onclick={() => setting(group.attribute, choice.id)}
							data-test-image-setting="{group.key}:{choice.id}">{choice.label}</button
						>
					{/each}
				</div>
			</div>
		{/each}
		<small class="ui-help">Le point d’intérêt est la partie gardée quand l’image est rognée.</small>
	{/if}

	{#if open}
		<MediaLibrary current={value} {label} onchoose={choose} onclose={close} />
	{/if}
</div>

<style>
	.current {
		display: flex;
		align-items: center;
		gap: var(--ui-space-2);
		min-height: calc(var(--ui-control) * 2);
		padding: var(--ui-space-1);
		border: var(--ui-border-width) solid var(--ui-border);
		border-radius: var(--ui-radius);
	}
	.current img {
		flex: none;
		width: var(--ui-thumb-width);
		height: var(--ui-thumb-height);
		object-fit: contain;
		border-radius: var(--ui-radius);
		background: var(--ui-bg-subtle);
	}
	.name {
		min-width: 0;
		overflow: hidden;
		font-size: var(--ui-text-md);
		text-overflow: ellipsis;
		white-space: nowrap;
		color: var(--ui-text);
	}
	.none {
		font-size: var(--ui-text-md);
		color: var(--ui-text-label);
	}
</style>
