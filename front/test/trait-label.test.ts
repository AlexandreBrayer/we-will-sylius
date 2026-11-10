// Libellés des champs du panneau (front/src/lib/editor/traits/registerTraits.ts, traitLabel) : tels que le registre
// les écrit, traits d'union compris, `npm test`.
import assert from 'node:assert/strict';
import { after, before, describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createServer, type ViteDevServer } from 'vite';

describe('libellés des champs', () => {
	let server: ViteDevServer;
	let traits: typeof import('../src/lib/editor/traits/registerTraits.ts');

	before(async () => {
		server = await createServer({
			root: fileURLToPath(new URL('../', import.meta.url)),
			logLevel: 'error',
			server: { middlewareMode: true, hmr: false, watch: null },
			appType: 'custom'
		});
		traits = (await server.ssrLoadModule('/src/lib/editor/traits/registerTraits.ts')) as typeof traits;
	});
	after(() => server.close());

	it('gardent leurs traits d’union (GrapesJS les changeait en espaces : « Sous titre »)', () => {
		const trait = (label: string, name = 'x') => ({ get: (key: string) => (key === 'label' ? label : undefined), getName: () => name });
		assert.equal(traits.traitLabel(trait('Sous-titre (facultatif)')), 'Sous-titre (facultatif)');
		assert.equal(traits.traitLabel(trait('Espace au-dessus')), 'Espace au-dessus');
		assert.equal(traits.traitLabel(trait('', 'cta2-url')), 'Cta2-url');
	});
});
