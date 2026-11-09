// Habillage du builder (front/src/lib/admin/admin.css) : sources lues comme des fichiers, `npm test`.
// Le builder ne charge pas la feuille de la boutique : il recopie les tokens du design system. Ce test les compare à
// assets/shop/styles/_tokens.scss (source unique) pour qu'aucune copie ne dérive, et garde les composants du builder
// sans couleur ni police en dur (AGENTS.md : on ajoute un token d'abord).
import assert from 'node:assert/strict';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { describe, it } from 'node:test';

const root = new URL('../../', import.meta.url);
const read = (path: string): string => readFileSync(new URL(path, root), 'utf8');
const ADMIN_CSS = read('front/src/lib/admin/admin.css');
const TOKENS = read('assets/shop/styles/_tokens.scss');

/** Fichiers `.svelte` du builder (éditeur, bandeau des listes, routes /cms-admin), chemins relatifs à la racine du dépôt. */
function builderComponents(dir = 'front/src'): string[] {
	return readdirSync(new URL(dir, root)).flatMap((name) => {
		const path = `${dir}/${name}`;
		if (statSync(new URL(path, root)).isDirectory()) {
			return dir === 'front/src' && !['lib', 'routes'].includes(name) ? [] : builderComponents(path);
		}
		const builder = /^front\/src\/(lib\/(editor|admin)|routes\/cms-admin)\//.test(path);
		return builder && name.endsWith('.svelte') ? [path] : [];
	});
}

/** Les déclarations `--yack-*` du bloc :root de admin.css. */
function rootTokens(): Map<string, string> {
	const block = /:root\s*\{([\s\S]*?)\n\}/.exec(ADMIN_CSS);
	assert.ok(block, 'bloc :root introuvable dans admin.css');
	const withoutComments = block[1].replace(/\/\*[\s\S]*?\*\//g, '');
	return new Map([...withoutComments.matchAll(/--(yack-[a-z0-9-]+):\s*([^;]+);/g)].map((m) => [m[1], m[2].trim()]));
}

/** Valeur d'un token `$nom: valeur;` de _tokens.scss. */
function scssToken(name: string): string {
	const match = new RegExp(`^\\$${name}:\\s*([^;]+);`, 'm').exec(TOKENS);
	assert.ok(match, `token $${name} introuvable dans _tokens.scss (à ajouter avant de l'utiliser dans le builder)`);
	return match[1].trim();
}

/** Couleur ramenée à une forme unique : `#rrggbb` ou `rgba(r, g, b, a)`. Résout `$token` et `rgba($token, a)`. */
function canonical(value: string): string {
	const text = value.trim().toLowerCase();
	if (text.startsWith('$')) return canonical(scssToken(text.slice(1)));
	const hex = /^#([0-9a-f]{3}|[0-9a-f]{6})$/.exec(text);
	if (hex) {
		const digits = hex[1].length === 3 ? [...hex[1]].map((c) => c + c).join('') : hex[1];
		return `#${digits}`;
	}
	const fn = /^rgba?\(\s*(.+?)\s*\)$/.exec(text);
	if (!fn) return text.replace(/\s+/g, ' ');
	const parts = fn[1].split(',').map((part) => part.trim());
	if (parts.length === 2) {
		const base = canonical(parts[0]);
		const [r, g, b] = [1, 3, 5].map((i) => Number.parseInt(base.slice(i, i + 2), 16));
		return `rgba(${r}, ${g}, ${b}, ${Number(parts[1])})`;
	}
	const [r, g, b, a = '1'] = parts;
	return `rgba(${Number(r)}, ${Number(g)}, ${Number(b)}, ${Number(a)})`;
}

describe('tokens du builder', () => {
	it('recopient fidèlement les tokens de la charte', () => {
		const tokens = rootTokens();
		assert.ok(tokens.size > 30, 'les tokens du design system sont déclarés');
		for (const [name, value] of tokens) {
			assert.equal(canonical(value), canonical(`$${name}`), `--${name} diffère de $${name} (_tokens.scss)`);
		}
	});

	it('gardent le nom historique des variables que les composants lisent', () => {
		const tokens = rootTokens();
		for (const name of ['yack-magenta', 'yack-magenta-dark', 'yack-dark', 'yack-gray', 'yack-border']) {
			assert.ok(tokens.has(name), `--${name} est lue par les composants du builder`);
		}
	});

	it('déclarent Lato avec les trois graisses de la charte', () => {
		for (const weight of [400, 700, 900]) {
			assert.match(ADMIN_CSS, new RegExp(`lato-latin-${weight}-normal\\.woff2`), `Lato ${weight} déclarée`);
		}
	});
});

describe('composants du builder', () => {
	it('ne portent aucune couleur en dur (tokens --yack-*)', () => {
		const offenders: string[] = [];
		for (const path of builderComponents()) {
			const style = /<style[^>]*>([\s\S]*?)<\/style>/.exec(read(path));
			if (!style) continue;
			const css = style[1].replace(/\/\*[\s\S]*?\*\//g, '');
			for (const color of css.match(/#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)|(?<![\w-])(?:white|black)(?![\w-])/g) ?? []) {
				offenders.push(`${path} : ${color}`);
			}
		}
		assert.deepEqual(offenders, []);
	});

	it('ne portent aucune police en dur (var(--yack-font) hérité, hors saisie de code)', () => {
		const offenders: string[] = [];
		for (const path of builderComponents()) {
			const style = /<style[^>]*>([\s\S]*?)<\/style>/.exec(read(path));
			if (!style) continue;
			for (const family of style[1].match(/font-family:\s*[^;]+/g) ?? []) {
				if (!/var\(--yack-font\)|ui-monospace/.test(family)) offenders.push(`${path} : ${family}`);
			}
		}
		assert.deepEqual(offenders, []);
	});
});
