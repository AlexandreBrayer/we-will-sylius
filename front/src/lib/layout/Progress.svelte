<script module lang="ts">
	// Yack progress indicator (registration, checkout): port of templates/shop/yack/layout/_progress.html.twig.
	export interface ProgressStep {
		label: string;
		/** data-test-progress-step value (defaults to the position). */
		name?: string;
		/** Link of a past step (only rendered when `linksEnabled`). */
		href?: string | null;
	}
</script>

<script lang="ts">
	let {
		steps,
		active,
		ariaLabel = 'Progression',
		linksEnabled = true,
		onselect
	}: {
		steps: ProgressStep[];
		/** Current step, 1-based. */
		active: number;
		ariaLabel?: string;
		linksEnabled?: boolean;
		/** Past steps become buttons calling this (client-side wizards) instead of links. */
		onselect?: (position: number) => void;
	} = $props();
</script>

<nav class="yack-progress mb-5" aria-label={ariaLabel}>
	<ol class="yack-progress__list">
		{#each steps as step, index (index)}
			{@const position = index + 1}
			{@const state = position < active ? 'done' : position === active ? 'active' : 'todo'}
			<li class="yack-progress__step is-{state}" aria-current={state === 'active' ? 'step' : undefined} data-test-progress-step={step.name ?? position}>
				<span class="yack-progress__bullet">{state === 'done' ? '✓' : position}</span>
				{#if state === 'done' && linksEnabled && onselect}
					<button type="button" class="yack-progress__label btn btn-link p-0 border-0 align-baseline" onclick={() => onselect(position)}>{step.label}</button>
				{:else if state === 'done' && linksEnabled && step.href}
					<a class="yack-progress__label" href={step.href}>{step.label}</a>
				{:else}
					<span class="yack-progress__label">{step.label}</span>
				{/if}
			</li>
		{/each}
	</ol>
</nav>
