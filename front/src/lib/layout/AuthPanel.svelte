<script lang="ts">
	import { RETURN_PARAM, SIGNUP_PATH } from '#lib/account/returnPath.js';

	// `back`: page to come back to after the sign-up (?redirect= of the login page), already checked.
	let { back = null }: { back?: string | null } = $props();
	const signupHref = $derived(back ? `${SIGNUP_PATH}?${new URLSearchParams({ [RETURN_PARAM]: back })}` : SIGNUP_PATH);
</script>

<!-- "Nouveau sur Clik&Yack ?" panel of the login page (templates/shop/yack/account/auth_panel). -->
<div class="d-flex flex-column justify-content-center align-items-center bg-light rounded-4 h-100 p-3">
	<div class="yack-auth-panel w-100 h-100 rounded-4 p-4 p-lg-5 d-flex flex-column justify-content-center" data-test-yack-auth-panel>
		<div class="yack-auth-panel__logo mb-4">
			<img src="/yack/logo-yack.png" alt="Yack — Clik&amp;Yack" class="yack-logo" width="1146" height="1031" />
		</div>
		<h2 class="h3 mb-3">Nouveau sur Clik&amp;Yack&nbsp;?</h2>
		<p class="mb-4">Créez votre compte en 4 étapes : profil, société, contact, adresses.</p>
		<ul class="list-unstyled mb-4">
			<li class="mb-2"><strong>Professionnels</strong> — SAV et pièces détachées par n° de série, commande en ligne sur demande.</li>
			<li class="mb-2"><strong>E-commerce</strong> — prix, disponibilités et commande en ligne dès l'activation.</li>
			<li class="mb-2">Escompte 2 % en payant par carte ou par virement sous 15 jours.</li>
		</ul>
		<div>
			<a href={signupHref} class="btn btn-light btn-lg px-4" data-test-register-here-button>Créer mon compte</a>
		</div>
	</div>
</div>
