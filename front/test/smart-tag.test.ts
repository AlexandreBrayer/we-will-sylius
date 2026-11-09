// Repère des fonctions intelligentes (ADR 0003) : sources lues comme des fichiers, `npm test`.
// Le pictogramme vit dans la pastille seule, jamais sur un bouton ; la teinte tient dans un seul token.
import assert from 'node:assert/strict';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { describe, it } from 'node:test';

const root = new URL('../../', import.meta.url);
const read = (path: string): string => readFileSync(new URL(path, root), 'utf8');
const TAG = read('front/src/lib/smart/SmartTag.svelte');
const TOKENS = read('assets/shop/styles/_tokens.scss');
const SMART = read('assets/shop/styles/_smart.scss');

/** Fichiers .svelte et .ts de front/src, chemins relatifs à la racine du dépôt. */
function sources(dir = 'front/src'): string[] {
	return readdirSync(new URL(dir, root)).flatMap((name) => {
		const path = `${dir}/${name}`;
		if (statSync(new URL(path, root)).isDirectory()) return sources(path);
		return /\.(svelte|ts)$/.test(name) ? [path] : [];
	});
}

/** Valeur d'un token `$nom: valeur;` de _tokens.scss. */
function token(name: string): string {
	const match = new RegExp(`^\\$${name}:\\s*([^;]+);`, 'm').exec(TOKENS);
	assert.ok(match, `token $${name} introuvable`);
	return match[1].trim();
}

describe('pastille « Fonction intelligente »', () => {
	it('porte un pictogramme décoratif, en currentColor', () => {
		const svg = /<svg[^>]*class="yack-smart-tag__icon"[^>]*>[\s\S]*?<\/svg>/.exec(TAG);
		assert.ok(svg, 'pictogramme absent de SmartTag.svelte');
		assert.match(svg[0], /aria-hidden="true"/);
		assert.match(svg[0], /fill="currentColor"/);
		assert.doesNotMatch(svg[0], /#[0-9a-f]{3,6}\b/i, 'aucune couleur dans le pictogramme');
	});

	it('garde le libellé dans un seul élément (pas d’écart de flex au milieu du texte)', () => {
		assert.match(TAG, /<span>\{label\}\{#if simulated\}/);
	});

	it('est le seul endroit du front qui dessine le pictogramme', () => {
		const chip = /const CHIP =\s*'([^']+)'/.exec(TAG);
		assert.ok(chip, 'tracé CHIP introuvable');
		const others = sources().filter((path) => !path.endsWith('/SmartTag.svelte') && read(path).includes(chip[1].slice(0, 40)));
		assert.deepEqual(others, []);
		assert.ok(!sources().some((path) => /yack-smart-tag__icon/.test(read(path)) && !path.endsWith('/SmartTag.svelte')));
	});
});

describe('teinte des fonctions intelligentes', () => {
	it('dérive les deux fonds du seul token $yack-smart-ink, couleur de la charte', () => {
		assert.match(token('yack-smart-ink'), /^\$yack-[a-z-]+$/, '$yack-smart-ink pointe une couleur nommée de la charte');
		for (const name of ['yack-smart-tag-bg', 'yack-smart-result-bg']) {
			const value = token(name);
			assert.match(value, /^color\.scale\(\$yack-smart-ink,/, `$${name} dérive de $yack-smart-ink`);
			assert.doesNotMatch(value, /#[0-9a-f]{3,6}\b/i);
		}
	});

	it('applique ces tokens à la pastille et au résultat, sans couleur en dur', () => {
		assert.match(SMART, /\.yack-smart-tag \{[^}]*background: \$yack-smart-tag-bg;[^}]*color: \$yack-smart-ink;/);
		assert.match(SMART, /&__result \{[^}]*background: \$yack-smart-result-bg;/);
		assert.doesNotMatch(SMART, /#[0-9a-f]{3,6}\b|rgba?\(/i);
	});
});
