// Espacement d'un bloc dans le builder (front/src/lib/editor/traits/spacing.ts, champ YackSpacing.svelte), `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { readStyle } from '../src/lib/blocks/style.ts';
import { pickStyle, styleAttributes, type Charte } from '../src/lib/editor/panels/blockStyle.ts';
import {
	commonValue,
	offCharteSides,
	parseSpacing,
	readSpacing,
	sidesAgree,
	spacingChanges,
	spacingText,
	SPACING_SIDES,
	targetSides,
	type SpacingValues
} from '../src/lib/editor/traits/spacing.ts';

const root = new URL('../../', import.meta.url);

/** Pas de la charte comme GET /builder/me les envoie (Charte.php). */
const CHARTE: Charte = {
	colors: [],
	titleSizes: [],
	spacings: [
		{ key: '0', label: 'Aucun', value: '0px' },
		{ key: 's', label: 'Petit', value: '16px' },
		{ key: 'm', label: 'Moyen', value: '32px' },
		{ key: 'l', label: 'Grand', value: '48px' },
		{ key: 'xl', label: 'Très grand', value: '80px' }
	],
	radii: []
};

const sides = (haut: string, droite: string, bas: string, gauche: string): SpacingValues => ({ hautEspace: haut, droiteEspace: droite, basEspace: bas, gaucheEspace: gauche });
const ALL = SPACING_SIDES.map(({ property }) => property);

/** Les objets `style:` des pages et templates des fixtures CMS (config/yack/fixtures_cms.yaml), lus ligne à ligne. */
function fixtureStyles(): Record<string, string>[] {
	const lines = readFileSync(new URL('config/yack/fixtures_cms.yaml', root), 'utf8').split('\n');
	const styles: Record<string, string>[] = [];
	lines.forEach((line, index) => {
		const head = /^(\s*)style:\s*$/.exec(line);
		if (!head) return;
		const style: Record<string, string> = {};
		for (const next of lines.slice(index + 1)) {
			const entry = /^(\s*)([A-Za-z]+):\s*'([^']*)'\s*$/.exec(next);
			if (!entry || entry[1].length <= head[1].length) break;
			style[entry[2]] = entry[3];
		}
		styles.push(style);
	});
	return styles;
}

describe('espacement : quatre côtés, un cadenas', () => {
	it('range les côtés dans le sens de la maquette : haut, droite, bas, gauche', () => {
		assert.deepEqual(ALL, ['hautEspace', 'droiteEspace', 'basEspace', 'gaucheEspace']);
		assert.deepEqual(
			SPACING_SIDES.map(({ label }) => label),
			['Espace en haut', 'Espace à droite', 'Espace en bas', 'Espace à gauche']
		);
	});

	it('lit les quatre attributs yb-* du composant, vide quand rien n’est posé', () => {
		assert.deepEqual(readSpacing({ 'yb-hautEspace': 'm', 'yb-gaucheEspace': '120px', 'yb-fond': 'plum', hautEspace: 'xl' }), sides('m', '', '', '120px'));
		assert.deepEqual(readSpacing({ 'yb-basEspace': 3 }), sides('', '', '', ''));
	});

	it('lie les côtés à l’ouverture seulement quand ils sont égaux : sinon le cadenas s’ouvre tout seul', () => {
		assert.equal(sidesAgree(sides('', '', '', '')), true);
		assert.equal(sidesAgree(sides('m', 'm', 'm', 'm')), true);
		assert.equal(sidesAgree(sides('m', '', '', '')), false);
		assert.equal(sidesAgree(sides('l', '', 'l', '')), false);
		assert.equal(sidesAgree(sides('s', 'm', 'l', 'xl')), false);
	});

	it('pose une valeur sur les quatre côtés liés, ou sur le seul côté choisi, en un seul changement', () => {
		assert.deepEqual(targetSides(true, 'basEspace'), ALL);
		assert.deepEqual(targetSides(false, 'basEspace'), ['basEspace']);
		assert.deepEqual(spacingChanges(sides('', '', '', ''), ALL, 'm'), {
			'yb-hautEspace': 'm',
			'yb-droiteEspace': 'm',
			'yb-basEspace': 'm',
			'yb-gaucheEspace': 'm'
		});
		assert.deepEqual(spacingChanges(sides('m', 'l', 'm', 'm'), ALL, 'm'), { 'yb-droiteEspace': 'm' });
		assert.deepEqual(spacingChanges(sides('m', 'l', 'm', 'm'), ['droiteEspace'], ''), { 'yb-droiteEspace': '' });
		assert.equal(spacingChanges(sides('m', 'm', 'm', 'm'), ALL, 'm'), null, 'rien ne change : aucune écriture, aucun pas d’annulation');
	});

	it('coche dans le menu des pas la valeur commune aux côtés visés, rien quand ils diffèrent', () => {
		assert.equal(commonValue(sides('m', 'm', 'm', 'm'), ALL), 'm');
		assert.equal(commonValue(sides('', '', '', ''), ALL), '');
		assert.equal(commonValue(sides('m', '', '', ''), ALL), null);
		assert.equal(commonValue(sides('m', '', '', ''), ['hautEspace']), 'm');
	});
});

describe('espacement : saisie d’un champ', () => {
	it('accepte un pas de la charte sans tenir compte de la casse, et le montre en capitales', () => {
		for (const [input, value] of [
			['M', 'm'],
			[' xl ', 'xl'],
			['0', '0'],
			['S', 's']
		]) {
			assert.deepEqual(parseSpacing(input, CHARTE, false), { ok: true, value }, input);
			assert.deepEqual(parseSpacing(input, CHARTE, true), { ok: true, value }, input);
		}
		assert.equal(spacingText('m'), 'M');
		assert.equal(spacingText('0'), '0');
		assert.equal(spacingText('120px'), '120px');
		assert.equal(spacingText(''), '');
	});

	it('vide : revient au réglage par défaut', () => {
		assert.deepEqual(parseSpacing('  ', CHARTE, false), { ok: true, value: '' });
	});

	it('px libres pour les comptes autorisés, bornés comme hautEspace ; une valeur de la charte devient sa clé', () => {
		assert.deepEqual(parseSpacing('40', CHARTE, true), { ok: true, value: '40px' });
		assert.deepEqual(parseSpacing('40PX', CHARTE, true), { ok: true, value: '40px' });
		assert.deepEqual(parseSpacing('32', CHARTE, true), { ok: true, value: 'm' });
		assert.deepEqual(parseSpacing('0px', CHARTE, true), { ok: true, value: '0' });
		assert.deepEqual(parseSpacing('200', CHARTE, true), { ok: true, value: '200px' });
		for (const input of ['201', '-4', '12.5', 'grand', '1e2']) {
			const result = parseSpacing(input, CHARTE, true);
			assert.equal(result.ok, false, input);
			assert.match(result.ok ? '' : result.message, /^Un pas de la charte \(0, S, M, L, XL\) ou nombre entier de 0 à 200 px attendu\.$/);
		}
	});

	it('niveau Charte : pas de px', () => {
		assert.deepEqual(parseSpacing('40', CHARTE, false), { ok: false, message: 'Choisissez un pas de la charte : 0, S, M, L ou XL.' });
	});

	it('signale les côtés hors charte, dans l’ordre des côtés', () => {
		assert.deepEqual(
			offCharteSides(sides('120px', 'm', '', '7px')).map(({ property }) => property),
			['hautEspace', 'gaucheEspace']
		);
		assert.deepEqual(offCharteSides(sides('m', '0', '', 'xl')), []);
	});
});

describe('espacement : le format enregistré ne change pas', () => {
	it('les styles des fixtures, rechargés puis réenregistrés sans action, donnent le même JSON', () => {
		const styles = fixtureStyles();
		assert.ok(styles.length >= 5, 'styles des fixtures lus');
		assert.ok(styles.some((style) => style.hautEspace === '120px'), 'dont une valeur hors charte');
		for (const style of styles) {
			// Chargement : le style de l'API devient les attributs yb-* du composant (comme avant le champ).
			const attributes = styleAttributes(style);
			const values = readSpacing(attributes);
			// Ouverture du panneau, sans action : le champ ne propose aucune écriture, même cadenas fermé.
			const linked = sidesAgree(values);
			const shown = commonValue(values, targetSides(linked, 'hautEspace'));
			assert.equal(shown === null ? null : spacingChanges(values, targetSides(linked, 'hautEspace'), shown), null);
			assert.equal(Object.hasOwn(attributes, 'yb-espacement'), false);
			// Enregistrement : même JSON qu'au chargement.
			assert.equal(JSON.stringify(pickStyle(attributes)), JSON.stringify(readStyle(style)), JSON.stringify(style));
		}
	});

	it('un bloc des fixtures aux côtés différents ouvre le cadenas (une saisie n’écrase pas les autres côtés)', () => {
		const top = fixtureStyles().find((style) => style.hautEspace === 'l' && style.basEspace === undefined);
		assert.ok(top);
		const values = readSpacing(styleAttributes(top));
		assert.equal(sidesAgree(values), false);
		assert.deepEqual(spacingChanges(values, targetSides(false, 'basEspace'), 'm'), { 'yb-basEspace': 'm' });
	});
});
