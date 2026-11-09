<script lang="ts">
	// Photo of a rating plate, inside a remote form (field `photo`): « La pièce par une photo » of /sav
	// (NameplatePanel) and any other form that reads a plate. The parent owns the form, its
	// submit button and its API errors; this component owns the file input and what happens before the upload.
	// Without JavaScript: one file input (the camera on a phone, `capture`), the file is sent as chosen. With
	// JavaScript: « Prendre une photo » (touch screens) and « Choisir une image », the photo reduced in the browser
	// (#lib/sav/photo.ts), a preview, a busy state while preparing. `use(file)` puts a file in the input as if it had
	// been chosen (demo plates: DemoPlates).
	import { onDestroy } from 'svelte';
	import type { RemoteFormField } from '$app/server';
	import Icon from '#lib/layout/Icon.svelte';
	import { NAMEPLATE_ACCEPT, formatBytes, photoIssue } from './nameplate.js';
	import { preparePhoto, replaceFile } from './photo.js';

	let {
		field,
		id,
		test,
		label = 'Photographier la plaque',
		busy = false,
		error = $bindable(null),
		preparing = $bindable(false),
		onchosen
	}: {
		/** `photo` field of the remote form. */
		field: RemoteFormField<File>;
		/** Id of the file input; its error is `<id>-error`. */
		id: string;
		/** Prefix of the `data-test-*` attributes (`sav-photo` gives `data-test-sav-photo-input`…). */
		test: string;
		label?: string;
		/** The form is sending or reading: the buttons wait. */
		busy?: boolean;
		/** Message of the browser (photo refused before the upload, photo missing), above the API issues of the field. */
		error?: string | null;
		/** The photo is being reduced. */
		preparing?: boolean;
		/** The file the form sends (reduced or not), null when none is ready. */
		onchosen?: (file: File | null) => void;
	} = $props();

	let input: HTMLInputElement | undefined = $state();
	/** JavaScript runs: the two buttons replace the bare input. */
	let enhanced = $state(false);
	/** Touch screen: the camera button comes first. */
	let touch = $state(false);
	let chosen: { name: string; size: number; reduced: boolean; preview: string } | null = $state(null);

	const waiting = $derived(preparing || busy);
	const photoError = $derived(error ?? field.issues()?.[0]?.message ?? null);
	const errorId = $derived(`${id}-error`);
	const data = (name: string) => ({ [`data-test-${test}-${name}`]: true });

	$effect(() => {
		enhanced = true;
		touch = window.matchMedia('(pointer: coarse)').matches;
	});

	// A reset of the form drops the preview with the file.
	$effect(() => {
		const form = input?.form;
		if (!form) return;
		const onreset = () => clear();
		form.addEventListener('reset', onreset);
		return () => form.removeEventListener('reset', onreset);
	});

	onDestroy(() => forget());

	function forget() {
		if (chosen) URL.revokeObjectURL(chosen.preview);
		chosen = null;
	}

	function clear() {
		forget();
		onchosen?.(null);
	}

	/** Opens the camera (`capture`) or the file chooser, on the same input. */
	function pick(camera: boolean) {
		if (!input || waiting) return;
		if (camera) input.setAttribute('capture', 'environment');
		else input.removeAttribute('capture');
		input.click();
	}

	/** Keeps the remote form in step with the file of the input (reduced or removed). */
	function sync(element: HTMLInputElement) {
		element.dispatchEvent(new Event('input', { bubbles: true }));
	}

	async function onPhoto(event: Event) {
		if (!enhanced) return;
		const element = event.currentTarget as HTMLInputElement;
		await accept(element, element.files?.[0]);
	}

	/** Puts `file` in the input and prepares it, as a chosen photo; false when it cannot be sent. */
	export async function use(file: File): Promise<boolean> {
		if (!input || waiting || !replaceFile(input, file)) return false;
		sync(input);
		return accept(input, file);
	}

	/** Prepares the file of the input (reduced when needed); true when a photo is ready to be sent. */
	async function accept(element: HTMLInputElement, file: File | undefined): Promise<boolean> {
		clear();
		error = null;
		if (!file) return false;

		preparing = true;
		try {
			const prepared = await preparePhoto(file);
			if ('error' in prepared) {
				error = prepared.error;
				element.value = '';
				sync(element);
				return false;
			}
			let sent = prepared.file;
			if (sent !== file && replaceFile(element, sent)) {
				sync(element);
			} else if (sent !== file) {
				// The browser keeps the original: sent as is when the API reads it.
				sent = file;
				const issue = photoIssue(file);
				if (issue) {
					error = issue;
					element.value = '';
					sync(element);
					return false;
				}
			}
			chosen = { name: sent.name, size: sent.size, reduced: sent !== file, preview: URL.createObjectURL(sent) };
			onchosen?.(sent);
			return true;
		} finally {
			preparing = false;
		}
	}
</script>

<label class="form-label fw-bold" class:visually-hidden={enhanced} for={id}>{label}</label>
<input
	{...field.as('file')}
	bind:this={input}
	{id}
	class="form-control"
	class:visually-hidden={enhanced}
	class:is-invalid={!!photoError}
	accept={NAMEPLATE_ACCEPT}
	capture="environment"
	required
	tabindex={enhanced ? -1 : undefined}
	aria-describedby={photoError ? errorId : undefined}
	onchange={onPhoto}
	{...data('input')}
/>

{#if enhanced}
	<div class="yack-sav-photo__pick">
		{#if touch}
			<button type="button" class="btn yack-smart-btn" disabled={waiting} onclick={() => pick(true)} {...data('camera')}>
				<Icon name="camera" />{chosen ? 'Reprendre la photo' : 'Prendre une photo'}
			</button>
		{/if}
		<button type="button" class="btn btn-outline-secondary yack-sav-photo__choose" disabled={waiting} onclick={() => pick(false)} {...data('choose')}>
			{chosen && !touch ? 'Choisir une autre image' : 'Choisir une image'}
		</button>
	</div>
{/if}

{#if preparing}
	<p class="yack-sav-photo__status" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Préparation de la photo…</p>
{:else if chosen}
	<div class="yack-sav-photo__chosen" {...data('chosen')}>
		<img class="yack-sav-photo__preview" src={chosen.preview} alt="Aperçu de la plaque choisie" />
		<span class="yack-sav-photo__file">
			<span class="text-break">{chosen.name}</span>
			<span class="d-block small">{formatBytes(chosen.size)}{chosen.reduced ? ', réduite pour l’envoi' : ''}</span>
		</span>
	</div>
{/if}

{#if photoError}<div class="invalid-feedback d-block" id={errorId} role="alert" data-test-field-error="photo">{photoError}</div>{/if}
