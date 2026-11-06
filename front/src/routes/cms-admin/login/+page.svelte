<script lang="ts">
	let { form } = $props();
</script>

<svelte:head><title>Connexion au builder | Clik&amp;Yack</title></svelte:head>

<div class="login-page">
	<form class="login-card" method="POST">
		<img class="yb-logo" src="/yack/logo-yack.png" alt="Yack" />
		<h1>Builder de pages</h1>
		<p class="hint">Connectez-vous avec votre compte administrateur Sylius.</p>
		{#if form?.error}<p class="yb-error" role="alert">{form.error}</p>{/if}
		<label class="yb-field">E-mail <input name="email" type="email" autocomplete="username" value={form?.email ?? ''} required /></label>
		<label class="yb-field">Mot de passe <input name="password" type="password" autocomplete="current-password" required /></label>
		<button class="yb-btn yb-btn-primary" type="submit">Se connecter</button>
	</form>
</div>

<style>
	.login-page {
		min-height: 100vh;
		display: flex;
		align-items: center;
		justify-content: center;
		background: linear-gradient(135deg, #a7357f 0%, #6e2254 100%);
	}
	.login-card {
		width: min(380px, 90vw);
		padding: 2rem;
		border-radius: 0.75rem;
		background: #fff;
		box-shadow: 0 1.5rem 3rem rgba(0, 0, 0, 0.25);
	}
	h1 {
		margin: 1rem 0 0.25rem;
		font-size: 1.4rem;
		color: var(--yack-magenta);
	}
	.hint {
		margin: 0 0 1.25rem;
		color: var(--yack-gray);
		font-size: 0.9rem;
	}
	button {
		width: 100%;
		justify-content: center;
	}
</style>
