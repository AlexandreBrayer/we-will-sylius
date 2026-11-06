<script lang="ts">
	// PRO → ECOM "commander en ligne" request (port of templates/shop/yack/account/order_access_request.html.twig).
	import { page } from '$app/state';
	import Field from '#lib/account/Field.svelte';
	import { formatDate, formatDateTime } from '#lib/format.js';
	import { getOrderAccess, requestOrderAccess } from '#lib/remote/account.remote.js';

	const access = $derived(await getOrderAccess());
	const draft = $derived(access.draft);
	const pending = $derived(access.latest?.status === 'pending' ? access.latest : null);

	// Instance of this visit (`.for`): its errors do not come back on the next one.
	const form = requestOrderAccess.for('demande');
	const fields = form.fields;
	const error = $derived(fields.issues()?.[0]?.message);
	const addressError = $derived(fields.address.issues()?.[0]?.message);
	const messageError = $derived(fields.message.issues()?.[0]?.message);
</script>

<svelte:head>
	<title>Commander en ligne | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="row g-5">
	<div class="col-lg-4">
		<h1 class="mb-3">Commander en ligne</h1>
		<p class="lead">Passez vos commandes Clik&amp;Yack directement sur le site, 24 h/24.</p>
		<ul class="list-unstyled text-body-secondary">
			<li class="mb-2">✓ Prix et disponibilités en temps réel</li>
			<li class="mb-2">✓ Pièces détachées commandables depuis le n° de série</li>
			<li class="mb-2">✓ Escompte en payant par carte ou par virement sous 15 jours</li>
			<li class="mb-2">✓ Suivi de vos commandes dans votre espace client</li>
		</ul>
	</div>

	<div class="col-lg-8">
		{#if page.url.searchParams.get('envoyee') === '1'}
			<div class="alert alert-success" role="status" data-test-order-access-sent>
				Votre demande d'ouverture à la commande en ligne a bien été envoyée. Votre agence Yack revient vers vous sous 48 h ouvrées.
			</div>
		{/if}

		{#if !access.canRequest}
			<div class="card border-success" data-test-yack-order-access="open">
				<div class="card-body p-4">
					<span class="badge text-bg-success mb-3">Commande en ligne ouverte</span>
					<h2 class="h4">Votre compte est ouvert à la commande en ligne</h2>
					<p class="mb-3">Prix, disponibilités et panier sont disponibles sur tout le catalogue.</p>
					<a href="/produits" class="btn btn-primary">Voir les produits</a>
				</div>
			</div>
		{:else if pending}
			<div class="card border-warning" data-test-yack-order-access="pending">
				<div class="card-body p-4">
					<span class="badge text-bg-warning mb-3">Demande en cours</span>
					<h2 class="h4">Votre demande est en cours de traitement</h2>
					<p class="mb-3">Envoyée le {formatDateTime(pending.createdAt)} pour <strong>{pending.company}</strong> (SIRET {pending.siret}).</p>
					<p class="text-body-secondary mb-0">
						Votre agence Yack vérifie votre dossier et vous confirme l'ouverture à la commande par e-mail à {pending.email}. Dès l'acceptation, les
						prix et le panier apparaissent sur votre compte.
					</p>
				</div>
			</div>
		{:else}
			{#if access.latest?.status === 'refused'}
				<div class="alert alert-secondary" data-test-yack-order-access="refused">
					Votre précédente demande du {formatDate(access.latest.createdAt)} n'a pas été acceptée. Vous pouvez en déposer une nouvelle ou contacter votre
					agence Yack.
				</div>
			{/if}

			<div class="card" data-test-yack-order-access="form">
				<div class="card-body p-4">
					<h2 class="h4 mb-1">Demandez l'ouverture de votre compte à la commande</h2>
					<p class="text-body-secondary">Les informations de votre compte sont pré-remplies : vérifiez-les et complétez l'adresse.</p>
					{#if error}
						<div class="alert alert-danger" role="alert">{error}</div>
					{/if}
					<form {...form} novalidate>
						<div class="row g-3">
							<div class="col-md-7"><Field field={fields.company} id="company" label="Société" value={draft?.company} required /></div>
							<div class="col-md-5">
								<Field field={fields.siret} id="siret" label="SIRET" value={draft?.siret} required inputmode="numeric" maxlength={17} />
							</div>
							<div class="col-md-12"><Field field={fields.fullName} id="fullName" label="Nom et prénom" value={draft?.fullName} required /></div>
							<div class="col-md-7"><Field field={fields.email} id="email" type="email" label="E-mail" value={draft?.email} required /></div>
							<div class="col-md-5"><Field field={fields.phone} id="phone" type="tel" label="Téléphone" value={draft?.phone} required /></div>
							<div class="col-md-12">
								<div class="field required">
									<label for="address" class="form-label required">Adresse de l'entreprise</label>
									<textarea {...fields.address.as('text', draft?.address ?? '')} id="address" rows="3" class="form-control" class:is-invalid={!!addressError}
									></textarea>
									{#if addressError}<div class="invalid-feedback d-block">{addressError}</div>{/if}
								</div>
							</div>
							<div class="col-md-12">
								<div class="field">
									<label for="message" class="form-label">Message (facultatif)</label>
									<textarea
										{...fields.message.as('text', draft?.message ?? '')}
										id="message"
										rows="4"
										class="form-control"
										class:is-invalid={!!messageError}
										placeholder="Volumes estimés, agence habituelle, besoins particuliers…"
									></textarea>
									{#if messageError}<div class="invalid-feedback d-block">{messageError}</div>{/if}
								</div>
							</div>
						</div>
						<button type="submit" class="btn btn-primary btn-lg mt-3" disabled={form.pending > 0} data-test-yack-order-access-submit>Envoyer ma demande</button>
					</form>
				</div>
			</div>
		{/if}
	</div>
</div>
