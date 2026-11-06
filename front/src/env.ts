import { defineEnvVars } from '@sveltejs/kit/env';

// Environment of the front (read at runtime, exposed by $app/env/private).
export const variables = defineEnvVars({
	SYLIUS_API_URL: {
		description: 'Sylius as reached by the SvelteKit server (Docker: http://nginx). The browser only talks to the front.',
		schema: (value) => (value || 'http://localhost').replace(/\/+$/, '')
	}
});
