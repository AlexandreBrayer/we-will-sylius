// Cache « périmé pendant la mise à jour » de l'arbre du catalogue (B9-12) : plus aucun visiteur n'attend
// les ~60 appels à l'API qui reconstruisent l'arbre chaque minute. `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { staleCache } from '../src/lib/server/staleCache.ts';

function deferred<T>() {
	let resolve!: (value: T) => void;
	let reject!: (error: unknown) => void;
	const promise = new Promise<T>((ok, ko) => {
		resolve = ok;
		reject = ko;
	});
	return { promise, resolve, reject };
}

describe('staleCache', () => {
	it('fait attendre le premier appel seulement, puis sert la valeur sans recharger avant expiration', async () => {
		let clock = 0;
		let loads = 0;
		const cache = staleCache(async () => ++loads, 1000, () => clock);
		assert.equal(await cache.get(), 1);
		clock = 1000;
		assert.equal(await cache.get(), 1);
		assert.equal(loads, 1);
	});

	it('sert la valeur expirée tout de suite et ne lance qu’un rechargement à la fois', async () => {
		let clock = 0;
		const next = deferred<string>();
		const loads: Promise<string>[] = [Promise.resolve('ancien'), next.promise];
		let calls = 0;
		const cache = staleCache(() => loads[calls++], 1000, () => clock);
		assert.equal(await cache.get(), 'ancien');
		clock = 5000;
		assert.equal(await cache.get(), 'ancien');
		assert.equal(await cache.get(), 'ancien');
		assert.equal(calls, 2);
		next.resolve('nouveau');
		await next.promise;
		await Promise.resolve();
		assert.equal(await cache.get(), 'nouveau');
	});

	it('garde l’ancienne valeur si le rechargement échoue et réessaie à l’appel suivant', async () => {
		let clock = 0;
		let calls = 0;
		const cache = staleCache(
			async () => {
				calls++;
				if (calls === 2) throw new Error('API indisponible');
				return `v${calls}`;
			},
			1000,
			() => clock
		);
		assert.equal(await cache.get(), 'v1');
		clock = 5000;
		assert.equal(await cache.get(), 'v1');
		await new Promise((r) => setTimeout(r, 0));
		assert.equal(await cache.get(), 'v1');
		await new Promise((r) => setTimeout(r, 0));
		assert.equal(calls, 3);
		assert.equal(await cache.get(), 'v3');
	});

	it('remonte l’échec du premier chargement sans rien garder', async () => {
		let calls = 0;
		const cache = staleCache(async () => {
			calls++;
			if (calls === 1) throw new Error('API indisponible');
			return 'ok';
		}, 1000);
		await assert.rejects(cache.get(), /API indisponible/);
		assert.equal(await cache.get(), 'ok');
	});

	it('est branché sur l’arbre du catalogue', () => {
		const source = readFileSync(new URL('../src/lib/server/catalog.ts', import.meta.url), 'utf8');
		assert.match(source, /const tree = staleCache\(/);
		assert.match(source, /return tree\.get\(\);/);
	});
});
