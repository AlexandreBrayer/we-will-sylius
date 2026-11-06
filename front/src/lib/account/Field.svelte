<script lang="ts">
	// Labelled input with help text and French error (markup of the Sylius shop form theme).
	import type { HTMLInputAttributes } from 'svelte/elements';

	let {
		name,
		label,
		value = '',
		error = null,
		help = null,
		required = false,
		id = name,
		...attributes
	}: {
		name: string;
		label: string;
		/** Initial value (uncontrolled input: what the user types survives re-renders). */
		value?: string | null;
		error?: string | null;
		help?: string | null;
		required?: boolean;
		id?: string;
	} & Omit<HTMLInputAttributes, 'name' | 'value' | 'id' | 'required'> = $props();
</script>

<div class="field" class:required>
	<label for={id} class="form-label" class:required>{label}</label>
	<input
		{id}
		{name}
		class="form-control"
		class:is-invalid={!!error}
		defaultValue={value ?? ''}
		aria-invalid={error ? 'true' : undefined}
		aria-describedby={error ? `${id}-error` : help ? `${id}-help` : undefined}
		{...attributes}
	/>
	{#if error}<div class="invalid-feedback d-block" id="{id}-error" data-test-field-error={name}>{error}</div>{/if}
	{#if help}<div class="form-text" id="{id}-help">{help}</div>{/if}
</div>
