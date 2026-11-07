import { defineEnvVars } from '@sveltejs/kit/env';

// Environment of the front (read at runtime, exposed by $app/env/private).
export const variables = defineEnvVars({
	SYLIUS_API_URL: {
		description: 'Sylius as reached by the SvelteKit server (Docker: http://nginx). The browser only talks to the front.',
		schema: (value) => (value || 'http://localhost').replace(/\/+$/, '')
	},
	YACK_DEMO_BAR: {
		description: 'Barre de démonstration (bascule Visiteur / PRO / ECOM / Admin sur les comptes de démo) : "1" seulement en démo, jamais en production.',
		schema: (value) => value === '1'
	}
});
