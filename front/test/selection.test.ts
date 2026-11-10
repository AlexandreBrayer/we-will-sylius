// Gestes sur le bloc sélectionné du canevas (front/src/lib/editor/selection.ts, B8-5), `npm test`.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { clickGesture, keyAction, removeQuestion, spacingMarks, spacingText, tabAfterClick, type CanvasClick, type CanvasKey } from '../src/lib/editor/selection.ts';

const click = (overrides: Partial<CanvasClick> = {}): CanvasClick => ({ button: 0, detail: 1, sameBlock: true, onText: false, inTypedText: false, ...overrides });
const key = (overrides: Partial<CanvasKey> = {}): CanvasKey => ({
	key: 'Delete',
	ctrlKey: false,
	metaKey: false,
	altKey: false,
	shiftKey: false,
	place: 'canvas',
	inField: false,
	modalOpen: false,
	selected: true,
	...overrides
});

describe('onglets après un clic sur un bloc', () => {
	it('un clic sur un autre bloc ouvre Contenu, quel que soit l’onglet', () => {
		for (const tab of ['contenu', 'style', 'avance'] as const) assert.equal(tabAfterClick(tab, false), 'contenu');
	});

	it('les clics successifs sur le bloc sélectionné alternent Contenu, Style, Contenu', () => {
		let tab = tabAfterClick('style', false);
		const seen = [tab];
		for (let i = 0; i < 3; i++) seen.push((tab = tabAfterClick(tab, true)));
		assert.deepEqual(seen, ['contenu', 'style', 'contenu', 'style']);
	});

	it('depuis Avancé, un nouveau clic revient à Contenu', () => {
		assert.equal(tabAfterClick('avance', true), 'contenu');
	});
});

describe('geste d’un clic dans le canevas', () => {
	it('ouvre Contenu sur un bloc nouvellement sélectionné, même au deuxième clic d’un double clic', () => {
		assert.equal(clickGesture(click({ sameBlock: false })), 'open');
		assert.equal(clickGesture(click({ sameBlock: false, detail: 2, onText: true })), 'open');
	});

	it('bascule tout de suite sur le bloc déjà sélectionné, hors texte', () => {
		assert.equal(clickGesture(click()), 'switch');
	});

	it('attend un éventuel double clic sur un texte du bloc sélectionné', () => {
		assert.equal(clickGesture(click({ onText: true })), 'switch-later');
	});

	it('ne compte pas les clics suivants d’un double ou triple clic (une seule bascule)', () => {
		assert.equal(clickGesture(click({ detail: 2 })), 'none');
		assert.equal(clickGesture(click({ detail: 3, onText: true })), 'none');
	});

	it('ne bascule rien dans un texte en cours de saisie, ni pour un autre bouton que le principal', () => {
		assert.equal(clickGesture(click({ inTypedText: true, onText: true })), 'none');
		assert.equal(clickGesture(click({ inTypedText: true, sameBlock: false })), 'none');
		assert.equal(clickGesture(click({ button: 2 })), 'none');
	});
});

describe('touches sur le bloc sélectionné', () => {
	it('Suppr et Retour arrière demandent la suppression, Échap désélectionne', () => {
		assert.equal(keyAction(key()), 'remove');
		assert.equal(keyAction(key({ key: 'Backspace' })), 'remove');
		assert.equal(keyAction(key({ key: 'Escape' })), 'deselect');
		assert.equal(keyAction(key({ key: 'Escape', place: 'body' })), 'deselect');
		assert.equal(keyAction(key({ place: 'body' })), 'remove');
	});

	it('ne font rien dans un champ, un texte en saisie, hors du canevas, sans sélection ni avec un modificateur', () => {
		assert.equal(keyAction(key({ inField: true })), null);
		assert.equal(keyAction(key({ key: 'Escape', inField: true })), null);
		assert.equal(keyAction(key({ place: 'elsewhere' })), null);
		assert.equal(keyAction(key({ key: 'Escape', place: 'elsewhere' })), null);
		assert.equal(keyAction(key({ selected: false })), null);
		for (const modifier of ['ctrlKey', 'metaKey', 'altKey', 'shiftKey'] as const) assert.equal(keyAction(key({ [modifier]: true })), null, modifier);
		assert.equal(keyAction(key({ key: 'a' })), null);
	});

	it('ne font rien quand une fenêtre modale est ouverte, même si le focus est resté sur la page', () => {
		assert.equal(keyAction(key({ modalOpen: true })), null);
		assert.equal(keyAction(key({ modalOpen: true, place: 'body' })), null);
		assert.equal(keyAction(key({ key: 'Escape', modalOpen: true, place: 'body' })), null);
		assert.equal(keyAction(key({ key: 'Backspace', modalOpen: true, place: 'body' })), null);
	});
});

describe('question avant la suppression', () => {
	it('nomme le bloc, son titre et ce qu’il contient, et rappelle Ctrl+Z', () => {
		assert.deepEqual(removeQuestion('Titre', 'Nos engagements', 0), {
			title: 'Supprimer ce bloc ?',
			text: 'Le bloc « Titre » (Nos engagements) sera retiré de la page. Ctrl+Z (⌘+Z sur Mac) le rétablit.'
		});
		assert.match(removeQuestion('Section', '', 1).text, /^Le bloc « Section » et le bloc qu’il contient seront retirés de la page\. Ctrl\+Z \(⌘\+Z sur Mac\) les rétablit\.$/);
		assert.match(removeQuestion('Section', '', 3).text, /« Section » et les 3 blocs qu’il contient/);
	});
});

describe('repères d’espacement du bloc sélectionné', () => {
	it('sont masqués quand les quatre espacements sont « Par défaut »', () => {
		assert.equal(spacingMarks({}), null);
		assert.equal(spacingMarks({ fond: 'magenta', hautEspace: '' }), null);
	});

	it('montrent les quatre valeurs dès qu’une est posée : haut, droite, bas, gauche', () => {
		assert.deepEqual(spacingMarks({ hautEspace: 'm', gaucheEspace: '24px' }), { top: '↑ M', right: '→ défaut', bottom: '↓ défaut', left: '← 24 px' });
		assert.deepEqual(spacingMarks({ hautEspace: '0', droiteEspace: 's', basEspace: 'xl', gaucheEspace: 'l' }), { top: '↑ 0', right: '→ S', bottom: '↓ XL', left: '← L' });
	});

	it('écrivent un pas de la charte en capitales et une valeur libre en px', () => {
		assert.equal(spacingText('xl'), 'XL');
		assert.equal(spacingText('12.5px'), '12.5 px');
		assert.equal(spacingText(undefined), 'défaut');
	});
});
