<script lang="ts">
	// Labelled input of a remote form field, with help text and French error (markup of the Sylius shop form theme).
	import type { RemoteFormField } from '$app/server';
	import type { HTMLInputAttributes } from 'svelte/elements';

	let {
		field,
		id,
		label,
		type = 'text',
		value = '',
		error,
		help = null,
		required = false,
		...attributes
	}: {
		field: RemoteFormField<string>;
		id: string;
		label: string;
		type?: 'text' | 'email' | 'tel' | 'password';
		/** Value shown until the field is edited or submitted (API data, answer of a previous step). */
		value?: string | null;
		/** Error decided by the page (client-side step validation); default: the field's issues. */
		error?: string | null;
		help?: string | null;
		required?: boolean;
	} & Omit<HTMLInputAttributes, 'name' | 'value' | 'id' | 'type' | 'required'> = $props();

	const message = $derived(error === undefined ? (field.issues()?.[0]?.message ?? null) : error);
</script>

<div class="field" class:required>
	<label for={id} class="form-label" class:required>{label}</label>
	<input
		{...field.as(type, value ?? '')}
		{id}
		class="form-control"
		class:is-invalid={!!message}
		aria-invalid={message ? 'true' : undefined}
		aria-describedby={message ? `${id}-error` : help ? `${id}-help` : undefined}
		{...attributes}
	/>
	{#if message}<div class="invalid-feedback d-block" id="{id}-error" data-test-field-error={id}>{message}</div>{/if}
	{#if help}<div class="form-text" id="{id}-help">{help}</div>{/if}
</div>
