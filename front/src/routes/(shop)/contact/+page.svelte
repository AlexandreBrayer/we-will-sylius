<script lang="ts">
	// /contact: message e-mailed to Yack (sendContact, #lib/remote/contact.remote.ts). Prefilled with the
	// logged-in customer's identity (viewer of the request, getContactForm) and with `?sujet=` (link "Nous contacter" of a CMS block).
	import { page } from '$app/state';
	import Field from '#lib/account/Field.svelte';
	import { getContactForm, sendContact } from '#lib/remote/contact.remote.js';

	const contact = $derived(await getContactForm(page.url.searchParams.get('sujet') ?? ''));
	const defaults = $derived(contact.defaults);

	// Instance of this visit (`.for`): "message envoyé" and errors do not come back on the next one.
	const form = sendContact.for('contact');
	const fields = form.fields;
	const sent = $derived(form.result?.sent === true);
	// Issues without a field (429 "trop de messages", API error): above the form.
	const formError = $derived(fields.allIssues()?.find((issue) => !issue.path || issue.path.length === 0)?.message);
	const messageError = $derived(fields.message.issues()?.[0]?.message);
</script>

<svelte:head>
	<title>Nous contacter | Clik&amp;Yack</title>
	<meta name="description" content="Écrivez à l'équipe Clik&amp;Yack : produits, pièces détachées, SAV, compte professionnel." />
</svelte:head>

<div class="container my-5">
	<div class="row g-5">
		<div class="col-lg-4">
			<h1 class="mb-3">Nous contacter</h1>
			<p class="lead">Une question sur un produit, une pièce détachée, une commande ou votre compte ? Écrivez-nous.</p>
			<p class="text-body-secondary">
				Pour un appareil en panne, la recherche par numéro de série du <a href="/sav">SAV</a> donne directement les vues éclatées et les pièces.
			</p>
		</div>

		<div class="col-lg-8">
			{#if sent}
				<div class="alert alert-success" role="status" data-test-contact-sent>
					<strong>Message envoyé.</strong> L'équipe Clik&amp;Yack vous répond par e-mail.
				</div>
			{/if}

			<div class="card" data-test-contact-form>
				<div class="card-body p-4">
					{#if formError}
						<div class="alert alert-danger" role="alert" data-test-contact-error>{formError}</div>
					{/if}
					<form {...form} novalidate>
						<div class="row g-3">
							<div class="col-md-6"><Field field={fields.name} id="name" label="Nom et prénom" value={defaults.name} required autocomplete="name" /></div>
							<div class="col-md-6">
								<Field field={fields.email} id="email" type="email" label="E-mail" value={defaults.email} required autocomplete="email" />
							</div>
							<div class="col-md-6"><Field field={fields.company} id="company" label="Société (facultatif)" value={defaults.company} autocomplete="organization" /></div>
							<div class="col-md-6"><Field field={fields.phone} id="phone" type="tel" label="Téléphone (facultatif)" value={defaults.phone} autocomplete="tel" /></div>
							<div class="col-12">
								<Field field={fields.subject} id="subject" label="Sujet" value={defaults.subject} maxlength={contact.subjectMax} placeholder="Demande de contact" />
							</div>
							<div class="col-12">
								<div class="field required">
									<label for="message" class="form-label required">Message</label>
									<textarea
										{...fields.message.as('text', '')}
										id="message"
										rows="6"
										class="form-control"
										class:is-invalid={!!messageError}
										aria-invalid={messageError ? 'true' : undefined}
										aria-describedby={messageError ? 'message-error' : undefined}
										data-test-contact-message
									></textarea>
									{#if messageError}<div class="invalid-feedback d-block" id="message-error" data-test-field-error="message">{messageError}</div>{/if}
								</div>
							</div>
						</div>
						<p class="form-text mt-3 mb-0">Vos coordonnées servent uniquement à répondre à votre message.</p>
						<button type="submit" class="btn btn-primary btn-lg mt-3" disabled={form.pending > 0} data-test-contact-submit>Envoyer</button>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
