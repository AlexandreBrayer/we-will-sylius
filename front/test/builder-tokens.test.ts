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

	it('reprennent les mesures de la barre du haut du back-office (assets/admin/styles)', () => {
		const variables = read('assets/admin/styles/_variables.scss');
		const layout = read('assets/admin/styles/_layout.scss');
		const scss = (name: string): string => {
			const match = new RegExp(`^\\$${name}:\\s*([^;]+);`, 'm').exec(variables);
			assert.ok(match, `$${name} introuvable dans _variables.scss`);
			return match[1].trim();
		};
		const logo = /\.yack-admin-logo\s*\{[\s\S]*?img\s*\{[^}]*?height:\s*([^;]+);/.exec(layout);
		assert.ok(logo, 'hauteur du logo .yack-admin-logo introuvable dans _layout.scss');
		const css = (name: string): string | undefined => new RegExp(`--${name}:\\s*([^;]+);`).exec(ADMIN_CSS)?.[1].trim();
		assert.equal(css('yb-topbar-height'), scss('admin-topbar-height'));
		assert.equal(css('yb-topbar-gutter'), scss('admin-gutter'));
		assert.equal(css('yb-topbar-logo'), logo[1].trim());
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

// Mini-charte de l'outil (E1) : front/src/lib/editor/ui-tokens.css ne déclare que des `--ui-*` construites à partir des
// tokens `--yack-*` de admin.css et de tailles ; la maquette de référence front/static/ui-kit/panneau.html déclare
// exactement les mêmes `--ui-*` et des `--yack-*` égales à celles de admin.css, sans autre couleur en dur.
const UI_TOKENS = read('front/src/lib/editor/ui-tokens.css');
const MOCKUP = read('front/static/ui-kit/panneau.html');

/** Déclarations `--prefix-*` d'un texte CSS (commentaires retirés), dans l'ordre : nom => valeur. */
function declarations(css: string, prefix: 'ui' | 'yack'): Map<string, string> {
	const text = css.replace(/\/\*[\s\S]*?\*\//g, '');
	return new Map([...text.matchAll(new RegExp(`--(${prefix}-[a-z0-9-]+):\\s*([^;]+);`, 'g'))].map((m) => [m[1], m[2].trim()]));
}

/** Couleurs écrites en dur dans un texte CSS : hexadécimal, rgb(), hsl(), mots-clés de couleur courants. */
function colorLiterals(css: string): string[] {
	return css.match(/#[0-9a-fA-F]{3,8}\b|\b(?:rgba?|hsla?)\([^)]*\)|(?<![\w-])(?:white|black|red|gray|grey|silver)(?![\w-])/g) ?? [];
}

/** CSS de la maquette : feuilles <style> et attributs style="…". */
function mockupCss(): string {
	const sheets = [...MOCKUP.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/g)].map((m) => m[1]);
	const inline = [...MOCKUP.matchAll(/\sstyle="([^"]*)"/g)].map((m) => m[1]);
	return [...sheets, ...inline].join('\n');
}

describe("mini-charte de l'outil (ui-tokens.css)", () => {
	it('ne déclare que des variables --ui-* dans un seul bloc :root', () => {
		const text = UI_TOKENS.replace(/\/\*[\s\S]*?\*\//g, '');
		assert.match(text, /^\s*:root\s*\{[^{}]*\}\s*$/, 'un bloc :root, rien d’autre');
		const properties = [...text.matchAll(/([a-z0-9-]+)\s*:/g)].map((m) => m[1]);
		assert.ok(properties.length > 20);
		assert.deepEqual(
			properties.filter((name) => !name.startsWith('--ui-')),
			[],
			'seules des variables --ui-* sont déclarées'
		);
	});

	it('construit chaque variable à partir des tokens --yack-* de admin.css et de tailles, sans couleur en dur', () => {
		const ui = declarations(UI_TOKENS, 'ui');
		const yack = rootTokens();
		assert.deepEqual(colorLiterals(UI_TOKENS.replace(/\/\*[\s\S]*?\*\//g, '')), []);
		const seen = new Set<string>();
		for (const [name, value] of ui) {
			const reference = /^var\(--((?:yack|ui)-[a-z0-9-]+)\)$/.exec(value);
			if (reference) {
				const target = reference[1];
				if (target.startsWith('yack-')) assert.ok(yack.has(target), `--${name} lit --${target}, absente de admin.css`);
				else assert.ok(seen.has(target), `--${name} lit --${target}, à déclarer avant`);
			} else {
				assert.match(value, /^\d+(?:\.\d+)?(?:px|ms)?$/, `--${name} : ${value} n'est ni un token ni une taille`);
			}
			seen.add(name);
		}
	});

	it('pose les mesures de la décision E1 et lit l’accent dans la charte', () => {
		const ui = declarations(UI_TOKENS, 'ui');
		const expected: Record<string, string> = {
			'ui-accent': 'var(--yack-magenta)',
			'ui-font': 'var(--yack-font)',
			'ui-text-xs': '11px',
			'ui-text-sm': '12px',
			'ui-text-md': '13px',
			'ui-space-1': '4px',
			'ui-control': '28px',
			'ui-swatch': '20px',
			'ui-radius': '4px',
			'ui-panel-width': '300px',
			'ui-thumb-width': '64px',
			'ui-thumb-height': '40px'
		};
		for (const [name, value] of Object.entries(expected)) assert.equal(ui.get(name), value, `--${name}`);
		for (const step of ['50', '100', '200', '400', '700', '900']) assert.ok(ui.has(`ui-gray-${step}`), `--ui-gray-${step} déclaré`);
	});
});

describe('maquette du panneau (front/static/ui-kit/panneau.html)', () => {
	it('déclare exactement les variables --ui-* de ui-tokens.css, mêmes valeurs', () => {
		assert.deepEqual([...declarations(mockupCss(), 'ui')], [...declarations(UI_TOKENS, 'ui')]);
	});

	it('recopie les tokens --yack-* de admin.css à l’identique', () => {
		const yack = rootTokens();
		const copied = declarations(mockupCss(), 'yack');
		assert.ok(copied.size > 10);
		for (const [name, value] of copied) {
			assert.ok(yack.has(name), `--${name} absente de admin.css`);
			assert.equal(canonical(value), canonical(yack.get(name) ?? ''), `--${name} diffère de admin.css`);
		}
	});

	it('ne porte aucune couleur en dur hors des tokens --yack-* déclarés en tête', () => {
		const css = mockupCss().replace(/\/\*[\s\S]*?\*\//g, '').replace(/--yack-[a-z0-9-]+:\s*[^;]+;/g, '');
		assert.deepEqual(colorLiterals(css), []);
		const svgPaint = [...MOCKUP.matchAll(/\s(?:fill|stroke|stop-color|color)="([^"]*)"/g)].map((m) => m[1]);
		assert.deepEqual(
			svgPaint.filter((paint) => !['none', 'currentColor'].includes(paint)),
			[],
			'les SVG peignent en currentColor ou par classe'
		);
	});

	it('reste autonome : aucune ressource externe', () => {
		assert.deepEqual(MOCKUP.match(/(?:src|href)="(?:https?:)?\/\/[^"]*"|@import|<link\b/g) ?? [], []);
	});
});
