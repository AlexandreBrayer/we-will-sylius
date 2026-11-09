// Retour après connexion ou inscription : filtre des chemins (redirection ouverte) et liens des blocs. `npm test`.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { presetProfile, safeReturnPath, withReturn } from '../src/lib/account/returnPath.ts';

describe('safeReturnPath', () => {
	it('garde un chemin du site, avec sa requête et son ancre', () => {
		for (const path of ['/', '/nos-documentations', '/produits/pompe-a-chaleur-air-eau?page=2', '/assistance?q=SRK25ZS-W#reponse', '/produit/pompe-%C3%A0'])
			assert.equal(safeReturnPath(path), path);
	});

	it('refuse les adresses qui sortent du site', () => {
		const traps = [
			'//evil.example',
			'///evil.example',
			'/\\evil.example',
			'\\\\evil.example',
			'https://evil.example',
			'http:/evil.example',
			'javascript:alert(1)',
			'evil.example',
			'%2F%2Fevil.example',
			// Le navigateur retire tabulations et retours à la ligne d'une URL : « //evil.example ».
			'/\t/evil.example',
			'/\n/evil.example',
			'/\r/evil.example',
			'/ /evil.example',
			'/a\\b',
			'/\u0000x',
			'/\u007fx',
			// Hors ASCII : refusé par l'en-tête Location (500) ou trompeur à l'œil.
			'/ /evil.example',
			'/　/evil.example',
			'/／/evil.example',
			'/​/evil.example',
			'/pompe-à-chaleur',
			''
		];
		for (const trap of traps) assert.equal(safeReturnPath(trap), null, JSON.stringify(trap));
		assert.equal(safeReturnPath(null), null);
		assert.equal(safeReturnPath(undefined), null);
	});

	it('refuse un chemin de plus de 2 000 caractères', () => {
		assert.equal(safeReturnPath(`/${'a'.repeat(1999)}`)?.length, 2000);
		assert.equal(safeReturnPath(`/${'a'.repeat(2000)}`), null);
	});
});

describe('presetProfile', () => {
	it('accepte pro et ecom seulement', () => {
		assert.equal(presetProfile('pro'), 'pro');
		assert.equal(presetProfile('ecom'), 'ecom');
		for (const value of ['', 'PRO', 'admin', null, undefined]) assert.equal(presetProfile(value), '');
	});
});

describe('withReturn', () => {
	const here = '/nos-documentations';

	it('ajoute le retour à la connexion', () => {
		assert.equal(withReturn('/connexion', here), '/connexion?redirect=%2Fnos-documentations');
	});

	it("présélectionne le profil PRO et ajoute le retour à l'inscription", () => {
		assert.equal(withReturn('/inscription', here), '/inscription?profil=pro&redirect=%2Fnos-documentations');
	});

	it('encode la requête de la page d’origine', () => {
		const href = withReturn('/connexion', '/produits/pac?marque=yack&page=2');
		assert.equal(href, '/connexion?redirect=%2Fproduits%2Fpac%3Fmarque%3Dyack%26page%3D2');
		assert.equal(new URLSearchParams(href.split('?')[1]).get('redirect'), '/produits/pac?marque=yack&page=2');
	});

	it('garde un retour ou un profil déjà posés, et l’ancre', () => {
		assert.equal(withReturn('/connexion?redirect=%2Fsav', here), '/connexion?redirect=%2Fsav');
		assert.equal(withReturn('/inscription?profil=ecom', here), '/inscription?profil=ecom&redirect=%2Fnos-documentations');
		assert.equal(withReturn('/connexion#comptes-demo', here), '/connexion?redirect=%2Fnos-documentations#comptes-demo');
	});

	it('laisse les autres liens tels quels', () => {
		for (const href of ['', '/', '/sav', '/connexions', '/inscription/terminee', '/compte', 'https://example.org/connexion', 'mailto:sav@yack.test'])
			assert.equal(withReturn(href, here), href);
	});

	it("ne pose pas de retour refusé, ni vers la connexion ou l'inscription", () => {
		for (const back of ['//evil.example', '/\t/evil.example', 'https://evil.example', null, undefined, '/connexion', '/connexion?redirect=%2F', '/inscription', '/inscription/terminee'])
			assert.equal(withReturn('/connexion', back), '/connexion', JSON.stringify(back));
	});
});
