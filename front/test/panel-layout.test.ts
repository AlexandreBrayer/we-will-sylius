// Disposition de l'onglet Style du panneau du bloc (front/src/lib/editor/panels/panelLayout.ts), `npm test`.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { arrangeStyle, styleGroup } from '../src/lib/editor/panels/panelLayout.ts';

type Field = { name: string; property?: string };
const field = (name: string, property?: string): Field => ({ name, property });
const arrange = (fields: Field[]) => arrangeStyle(fields, (item) => item.property).map(({ group, items }) => [group.title, items.map((item) => item.name)]);

describe("onglet Style du panneau", () => {
	it('range les champs du bloc texte + image dans les groupes et l’ordre de la maquette', () => {
		// Ordre du registre : les champs communs (STYLE_TRAITS) arrivent après balise et alignement vertical, propres au bloc.
		const fields = [
			field('yb-balise', 'balise'),
			field('yb-alignementVertical', 'alignementVertical'),
			field('yb-fond', 'fond'),
			field('yb-texte', 'texte'),
			field('yb-accent', 'accent'),
			field('yb-titre', 'titre'),
			field('yb-hautEspace', 'hautEspace'),
			field('yb-basEspace', 'basEspace'),
			field('yb-gaucheEspace', 'gaucheEspace'),
			field('yb-droiteEspace', 'droiteEspace'),
			field('yb-largeur', 'largeur'),
			field('yb-alignement', 'alignement'),
			field('yb-arrondi', 'arrondi')
		];
		assert.deepEqual(arrange(fields), [
			['Couleurs', ['yb-fond', 'yb-texte', 'yb-accent']],
			['Titre', ['yb-balise', 'yb-titre']],
			[
				'Mise en page',
				['yb-hautEspace', 'yb-basEspace', 'yb-gaucheEspace', 'yb-droiteEspace', 'yb-largeur', 'yb-alignement', 'yb-alignementVertical', 'yb-arrondi']
			]
		]);
	});

	it('range la position du visuel (prop du bloc texte + image, nommée par son nom) avant l’arrondi, comme la maquette', () => {
		const fields = [field('imagePosition'), field('yb-arrondi', 'arrondi'), field('yb-alignementVertical', 'alignementVertical'), field('yb-largeur', 'largeur')];
		const byName = arrangeStyle(fields, (item) => item.property ?? item.name).map(({ group, items }) => [group.title, items.map((item) => item.name)]);
		assert.deepEqual(byName, [['Mise en page', ['yb-largeur', 'yb-alignementVertical', 'imagePosition', 'yb-arrondi']]]);
	});

	it('omet les groupes vides (conteneur : pas de titre ni d’accent)', () => {
		assert.deepEqual(arrange([field('yb-largeur', 'largeur'), field('yb-fond', 'fond'), field('yb-hautEspace', 'hautEspace')]), [
			['Couleurs', ['yb-fond']],
			['Mise en page', ['yb-hautEspace', 'yb-largeur']]
		]);
	});

	it('garde à la fin de « Mise en page », dans leur ordre, les champs que la maquette ne connaît pas', () => {
		assert.deepEqual(arrange([field('autre'), field('yb-arrondi', 'arrondi'), field('encore', 'inconnue'), field('yb-fond', 'fond')]), [
			['Couleurs', ['yb-fond']],
			['Mise en page', ['yb-arrondi', 'autre', 'encore']]
		]);
		assert.equal(styleGroup(undefined), 'mise-en-page');
		assert.equal(styleGroup('balise'), 'titre');
	});
});
