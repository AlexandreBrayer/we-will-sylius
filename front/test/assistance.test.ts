// Tests du module pur de /assistance (src/lib/assistance/assistance.ts) : `npm test`.
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
	FIELD_MESSAGES,
	MANUAL_TYPES,
	MORE_CHOICES_MESSAGE,
	PLATE_DEMOS,
	PLATE_MESSAGES,
	QUESTION_MESSAGES,
	REFERENCE_MAX,
	QUERY_MAX,
	answerSourceLabel,
	assistanceUrl,
	conflictMessage,
	contactUrl,
	indexedManuals,
	indexedPagesLabel,
	isEmptyInput,
	levelLabel,
	pagesLabel,
	pdfPageUrl,
	plateCheck,
	productUrl,
	questionLoginUrl,
	questionTarget,
	reasonLabel,
	searchForm,
	searchInput,
	sortLanguages,
	sourceLabel,
	toManualAnswer,
	toManualSearch,
	toPlateReading,
	typeLabel,
	unavailablePlate,
	validateFields,
	validationRetry
} from '../src/lib/assistance/assistance.ts';
import { demoPlateUrl } from '../src/lib/sav/nameplate.ts';

const DOCUMENT = {
	id: 'mhi-srk-zs-w-installation-rjg012a031',
	title: 'Installation manual SRK20ZS-W, SRK25ZS-W',
	type: 'installation',
	typeLabel: "Manuel d'installation",
	languages: ['en', 'fr'],
	url: 'https://example.org/manuels/rjg012a031.pdf',
	official: true,
	manualNumber: 'RJG012A031',
	year: '2019',
	pages: 24,
	models: ['SRK20ZS-W', 'SRK25ZS-W'],
	hasText: true,
	indexedPages: '12-19'
};

describe('levelLabel et reasonLabel', () => {
	it('donne le bandeau de chaque niveau', () => {
		assert.equal(levelLabel('exact'), 'Documents de ce modèle');
		assert.equal(levelLabel('serie'), 'Documents de la même série');
		assert.equal(levelLabel('famille'), 'Autre série du même type d’appareil');
		assert.equal(levelLabel('aucun'), 'Aucun manuel trouvé');
	});

	it('dit pourquoi un document est rangé à son niveau', () => {
		assert.equal(reasonLabel('variante_suffixe'), 'Même modèle, autre variante : vérifiez la liste des modèles en page de garde.');
		assert.equal(
			reasonLabel('autre_puissance', 'SRK50ZS-W'),
			'Documents de la même série, pas de ce modèle exact (SRK50ZS-W) : vérifiez la puissance dans la liste des modèles.'
		);
		assert.match(reasonLabel('meme_type_appareil'), /jamais pour une valeur technique/);
		assert.equal(reasonLabel(null), '');
	});
});

describe('typeLabel', () => {
	it("prend le libellé de l'API, sinon celui du front", () => {
		assert.equal(typeLabel('installation', ' Installation manual '), 'Installation manual');
		assert.equal(typeLabel('fiche_technique'), 'Fiche technique');
		assert.equal(typeLabel('autre', ''), 'Document');
	});

	it('connaît chaque type, dans l’ordre de l’API', () => {
		assert.deepEqual(MANUAL_TYPES, ['installation', 'utilisation', 'technique', 'service', 'fiche_technique', 'pieces', 'telecommande', 'brochure', 'autre']);
		for (const type of MANUAL_TYPES) assert.notEqual(typeLabel(type), '');
	});
});

describe('sortLanguages', () => {
	it('met le français en premier, garde l’ordre des autres, sans doublon', () => {
		assert.deepEqual(sortLanguages(['en', 'de', 'FR', 'en']), ['fr', 'en', 'de']);
		assert.deepEqual(sortLanguages(['en']), ['en']);
		assert.deepEqual(sortLanguages(['', 'français', 'it']), ['it']);
	});
});

describe('pagesLabel et conflictMessage', () => {
	it('écrit le nombre de pages', () => {
		assert.equal(pagesLabel(24), '24 p.');
		assert.equal(pagesLabel(null), '');
	});

	it('écrit le bandeau du conflit entre n° de série et référence', () => {
		const serial = { value: 'YK19KAZ35S004567', known: true, savUrl: '/sav/YK19KAZ35S004567', productCodes: ['SRK25ZS-WFT'], conflict: true };
		assert.equal(conflictMessage(serial, 'SRK35ZS-W'), 'Le n° de série correspond à SRK25ZS-WFT, la référence saisie est SRK35ZS-W : vérifiez la plaque.');
	});
});

describe('sourceLabel', () => {
	const serial = { value: 'YK19KAZ35S004567', known: true, savUrl: '/sav/YK19KAZ35S004567', productCodes: ['SRK25ZS-WFT', 'SRC25ZS-W2'], conflict: true };

	it('dit qu’un groupe vient du n° de série et de ses produits finis', () => {
		assert.equal(sourceLabel('serial', serial, 'SRK35ZS-W'), 'D’après le n° de série YK19KAZ35S004567 (SRK25ZS-WFT, SRC25ZS-W2)');
		assert.equal(sourceLabel('serial', null, null), 'D’après le n° de série');
	});

	it('dit qu’un groupe vient de la référence saisie', () => {
		assert.equal(sourceLabel('reference', serial, 'SRK35ZS-W'), 'D’après la référence saisie SRK35ZS-W');
		assert.equal(sourceLabel('reference', serial, null), 'D’après la référence saisie');
	});
});

describe('searchForm', () => {
	it('garde le n° de série validé en champ caché, la barre portant la référence', () => {
		assert.deepEqual(searchForm({ q: '', ref: 'SRK35ZS-W', sn: 'YK19KAZ35S004567', marque: 'MHI' }), {
			name: 'ref',
			value: 'SRK35ZS-W',
			serial: 'YK19KAZ35S004567',
			maxlength: REFERENCE_MAX
		});
	});

	it('reste une saisie libre sans n° de série à garder', () => {
		assert.deepEqual(searchForm({ q: 'mural titane', ref: '', sn: '', marque: '' }), { name: 'q', value: 'mural titane', serial: null, maxlength: QUERY_MAX });
		assert.deepEqual(searchForm({ q: '', ref: 'SRK25ZS-W', sn: '', marque: '' }), { name: 'q', value: 'SRK25ZS-W', serial: null, maxlength: QUERY_MAX });
		assert.deepEqual(searchForm({ q: '', ref: '', sn: 'YK19KAZ35S004567', marque: '' }), { name: 'q', value: 'YK19KAZ35S004567', serial: null, maxlength: QUERY_MAX });
		assert.deepEqual(searchForm({ q: 'SRK25ZS-W', ref: '', sn: 'YK19KAZ35S004567', marque: '' }), { name: 'q', value: 'SRK25ZS-W', serial: null, maxlength: QUERY_MAX });
	});
});

describe('assistanceUrl et searchInput', () => {
	it('construit l’URL de la page, champs vides omis', () => {
		assert.equal(assistanceUrl({}), '/assistance');
		assert.equal(assistanceUrl({ q: ' mural titane ' }), '/assistance?q=mural+titane');
		assert.equal(assistanceUrl({ ref: 'SRK25ZS-W', sn: '', marque: 'MHI' }), '/assistance?ref=SRK25ZS-W&marque=MHI');
		assert.equal(assistanceUrl({ q: 'SRK25ZS-W', ref: 'SRK35ZS-W' }), '/assistance?q=SRK25ZS-W');
	});

	it('lit la saisie de l’URL, `reference` et `serial` valant `ref` et `sn`', () => {
		assert.deepEqual(searchInput(new URLSearchParams('ref=SRK25ZS-W&sn=YK19KAZ35S004567&marque=MHI')), {
			q: '',
			ref: 'SRK25ZS-W',
			sn: 'YK19KAZ35S004567',
			marque: 'MHI'
		});
		assert.deepEqual(searchInput(new URLSearchParams('reference=SRK50ZS-W&serial=%20')), { q: '', ref: 'SRK50ZS-W', sn: '', marque: '' });
		assert.equal(isEmptyInput(searchInput(new URLSearchParams('marque=MHI&q=%20'))), true);
	});
});

describe('pdfPageUrl, productUrl, contactUrl', () => {
	it('ouvre le PDF https à la page donnée', () => {
		assert.equal(pdfPageUrl('https://example.org/m.pdf', 47), 'https://example.org/m.pdf#page=47');
		assert.equal(pdfPageUrl('https://example.org/m.pdf#page=3'), 'https://example.org/m.pdf');
		assert.equal(pdfPageUrl('https://example.org/m.pdf', 0), 'https://example.org/m.pdf');
	});

	it('refuse tout autre schéma que https', () => {
		assert.equal(pdfPageUrl('http://example.org/m.pdf'), null);
		assert.equal(pdfPageUrl('javascript:alert(1)'), null);
		assert.equal(pdfPageUrl('data:application/pdf;base64,AAAA'), null);
		assert.equal(pdfPageUrl('/manuel.pdf'), null);
	});

	it('mène à la fiche produit et au support', () => {
		assert.equal(productUrl({ code: 'SRK25ZS-W', name: 'Mural', slug: 'mural-zs-2-5-kw' }), '/produit/mural-zs-2-5-kw');
		assert.equal(productUrl({ code: 'SRK25ZS-W', name: 'Mural', slug: null }), null);
		assert.equal(contactUrl('ZZZ'), '/contact?sujet=Manuels%20et%20notices%20%3A%20aucun%20document%20pour%20ZZZ');
	});
});

describe('validateFields', () => {
	it('demande la référence ou le n° de série', () => {
		assert.deepEqual(validateFields({ ref: ' ', sn: '' }), { ref: FIELD_MESSAGES.required });
		assert.deepEqual(validateFields({ ref: 'SRK25ZS-W' }), {});
		assert.deepEqual(validateFields({ sn: 'YK19KAZ35S004567' }), {});
	});

	it('borne chaque champ', () => {
		assert.deepEqual(validateFields({ ref: 'A'.repeat(41), sn: 'B'.repeat(33), marque: 'C'.repeat(61) }), {
			ref: FIELD_MESSAGES.reference,
			sn: FIELD_MESSAGES.serial,
			marque: FIELD_MESSAGES.brand
		});
		assert.deepEqual(validateFields({ ref: 'A'.repeat(40), sn: 'B'.repeat(32), marque: 'C'.repeat(60) }), {});
		assert.deepEqual(validateFields({ q: 'Q'.repeat(101) }), { q: FIELD_MESSAGES.query });
		assert.deepEqual(validateFields({ q: 'mural titane', ref: 'A'.repeat(41) }), {});
	});
});

describe('toManualSearch', () => {
	it('lit une réponse complète', () => {
		const search = toManualSearch({
			query: 'srk25zs w',
			reference: { read: 'srk25zs w', canonical: 'SRK25ZS-W', structured: true, corrected: false, brand: 'MHI', brandSource: 'catalogue' },
			serial: null,
			product: { code: 'SRK25ZS-W', name: 'Mural ZS 2,5 kW', slug: 'mural-zs-2-5-kw' },
			level: 'exact',
			groups: [{ level: 'exact', reason: 'reference_identique', source: 'reference', documents: [DOCUMENT] }],
			choices: [],
			moreChoices: false,
			askable: true
		});
		assert.equal(search.level, 'exact');
		assert.equal(search.groups[0]?.source, 'reference');
		assert.equal(search.reference?.canonical, 'SRK25ZS-W');
		assert.equal(search.reference?.brandSource, 'catalogue');
		assert.deepEqual(search.groups[0]?.documents[0], { ...DOCUMENT, languages: ['fr', 'en'] });
		assert.equal(search.askable, true);
	});

	it('écarte un document sans URL https et un groupe vide, et ramène le niveau à ce qui reste', () => {
		const search = toManualSearch({
			level: 'exact',
			groups: [
				{ level: 'exact', reason: 'reference_identique', source: 'reference', documents: [{ ...DOCUMENT, url: 'javascript:alert(1)' }] },
				{ level: 'serie', reason: 'autre_puissance', source: 'reference', documents: [DOCUMENT, { id: '', title: 'x', url: 'https://e.org/x.pdf' }] }
			],
			askable: true
		});
		assert.equal(search.level, 'serie');
		assert.equal(search.groups.length, 1);
		assert.equal(search.groups[0]?.documents.length, 1);
	});

	it('garde les pages indexées d\'un document à texte seulement', () => {
		const search = toManualSearch({
			level: 'exact',
			groups: [
				{
					level: 'exact',
					reason: 'reference_identique',
					source: 'reference',
					documents: [
						{ ...DOCUMENT, id: 'a', indexedPages: undefined },
						{ ...DOCUMENT, id: 'b', hasText: false },
						{ ...DOCUMENT, id: 'c', indexedPages: ' 4-8, 10 ' }
					]
				}
			]
		});
		assert.deepEqual(
			search.groups[0]?.documents.map((doc) => [doc.id, doc.indexedPages]),
			[
				['a', null],
				['b', null],
				['c', '4-8, 10']
			]
		);
	});

	it('rend une recherche vide sur une réponse mal formée', () => {
		assert.deepEqual(toManualSearch(null), {
			query: '',
			reference: null,
			serial: null,
			product: null,
			level: 'aucun',
			groups: [],
			choices: [],
			moreChoices: false,
			askable: false
		});
	});

	it('écarte un groupe sans source reconnue', () => {
		const search = toManualSearch({
			level: 'exact',
			groups: [
				{ level: 'exact', reason: 'reference_identique', documents: [DOCUMENT] },
				{ level: 'exact', reason: 'reference_identique', source: 'plaque', documents: [DOCUMENT] }
			]
		});
		assert.deepEqual(search.groups, []);
		assert.equal(search.level, 'aucun');
	});

	it('garde les groupes des deux sources en cas de conflit', () => {
		const search = toManualSearch({
			reference: { read: 'SRK35ZS-W', canonical: 'SRK35ZS-W', structured: true, corrected: false, brand: 'MHI', brandSource: 'catalogue' },
			serial: { value: 'YK19KAZ35S004567', known: true, savUrl: '/sav/YK19KAZ35S004567', productCodes: ['SRK25ZS-WFT', 'SRC25ZS-W2'], conflict: true },
			level: 'exact',
			groups: [
				{ level: 'exact', reason: 'reference_identique', source: 'serial', documents: [{ ...DOCUMENT, id: 'srk-zs-wft' }] },
				{ level: 'exact', reason: 'reference_identique', source: 'reference', documents: [DOCUMENT] },
				{ level: 'serie', reason: 'variante_suffixe', source: 'serial', documents: [DOCUMENT] }
			],
			askable: true
		});
		assert.deepEqual(
			search.groups.map((group) => [group.level, group.source]),
			[
				['exact', 'serial'],
				['exact', 'reference'],
				['serie', 'serial']
			]
		);
		assert.equal(search.serial?.conflict, true);
	});

	it('garde le lien SAV seulement pour un n° connu et interne', () => {
		const known = toManualSearch({ serial: { value: 'YK19KAZ35S004567', known: true, savUrl: '/sav/YK19KAZ35S004567', productCodes: ['SRK25ZS-WFT'], conflict: true } });
		assert.equal(known.serial?.savUrl, '/sav/YK19KAZ35S004567');
		assert.equal(known.serial?.conflict, true);
		const outside = toManualSearch({ serial: { value: 'X', known: true, savUrl: 'https://ailleurs.example/sav', productCodes: [] } });
		assert.equal(outside.serial?.savUrl, null);
		const unknown = toManualSearch({ serial: { value: 'ABC123456', known: false, savUrl: '/sav/ABC123456' } });
		assert.equal(unknown.serial?.savUrl, null);
	});

	it('lit les choix de modèles', () => {
		const search = toManualSearch({ level: 'aucun', groups: [], choices: [{ code: 'SRK25ZS-WT', name: 'Mural titane 2,5 kW' }, { name: 'sans code' }] });
		assert.deepEqual(search.choices, [{ code: 'SRK25ZS-WT', name: 'Mural titane 2,5 kW' }]);
		assert.equal(search.moreChoices, false);
		assert.equal(search.level, 'aucun');
	});

	it('lit moreChoices seulement avec des choix', () => {
		const more = toManualSearch({ level: 'aucun', groups: [], choices: [{ code: 'SRK15ZS-WFT', name: 'Mural Titane 1,5 kW' }], moreChoices: true });
		assert.equal(more.moreChoices, true);
		assert.equal(toManualSearch({ level: 'aucun', groups: [], choices: [], moreChoices: true }).moreChoices, false);
		assert.equal(toManualSearch({ level: 'aucun', groups: [], choices: [{ code: 'A', name: 'A' }], moreChoices: 'oui' }).moreChoices, false);
		assert.equal(MORE_CHOICES_MESSAGE, 'Précisez la puissance ou la référence pour affiner.');
	});
});

describe('photo de la plaque', () => {
	const READING = { serialNumber: null, referenceRead: 'SRK2SZS-W', reference: 'SRK25ZS-W', model: 'Mural ZS 2,5 kW', brand: 'MHI', brandSource: 'deduite', simulated: true };

	it('lit la réponse de l\'API et écarte ce qui est mal formé', () => {
		assert.deepEqual(toPlateReading(READING), READING);
		assert.deepEqual(toPlateReading({ serialNumber: ' ', reference: 12, brand: 'MHI', brandSource: 'catalogue', simulated: 'oui' }), {
			serialNumber: null,
			referenceRead: null,
			reference: null,
			model: null,
			brand: 'MHI',
			brandSource: null,
			simulated: false
		});
		assert.equal(toPlateReading({ brandSource: 'plaque' }).brandSource, null);
		assert.equal(toPlateReading(null).simulated, false);
	});

	it('préremplit la forme comprise et dit ce qui est lu, corrigé ou déduit', () => {
		const check = plateCheck(toPlateReading(READING));
		assert.equal(check.status, 'read');
		assert.equal(check.message, null);
		assert.deepEqual(check.values, { ref: 'SRK25ZS-W', sn: '', marque: 'MHI' });
		assert.deepEqual(check.notes, { ref: 'lu : SRK2SZS-W, compris : SRK25ZS-W', sn: '', marque: 'déduite de la référence' });
		assert.equal(check.model, 'Mural ZS 2,5 kW');
		assert.equal(check.simulated, true);
	});

	it('dit « lue sur la plaque » quand la lecture et la forme comprise sont identiques', () => {
		const check = plateCheck(
			toPlateReading({ serialNumber: 'MH21ZSW0004242', referenceRead: 'SRK25ZS-W', reference: 'SRK25ZS-W', model: null, brand: 'MHI', brandSource: 'plaque', simulated: true })
		);
		assert.deepEqual(check.values, { ref: 'SRK25ZS-W', sn: 'MH21ZSW0004242', marque: 'MHI' });
		assert.deepEqual(check.notes, { ref: 'lue sur la plaque', sn: 'lu sur la plaque', marque: 'lue sur la plaque' });
	});

	it('garde le texte lu quand rien n\'est compris', () => {
		const check = plateCheck(toPlateReading({ referenceRead: 'RC-EX3D', reference: null, simulated: false }));
		assert.equal(check.status, 'read');
		assert.equal(check.values.ref, 'RC-EX3D');
		assert.equal(check.notes.ref, 'lue sur la plaque');
	});

	it('ouvre l\'écran de saisie, champs vides, sur une plaque illisible ou une lecture indisponible', () => {
		const unreadable = plateCheck(toPlateReading({ serialNumber: null, referenceRead: null, reference: null, model: null, brand: null, brandSource: null, simulated: true }));
		assert.equal(unreadable.status, 'unreadable');
		assert.equal(unreadable.message, PLATE_MESSAGES.unreadable);
		assert.equal(unreadable.message, 'Plaque illisible : saisissez la référence');
		assert.deepEqual(unreadable.values, { ref: '', sn: '', marque: '' });
		assert.equal(unreadable.simulated, true);

		const unavailable = unavailablePlate();
		assert.equal(unavailable.status, 'unavailable');
		assert.equal(unavailable.message, 'Lecture indisponible : saisissez la référence');
		assert.deepEqual(unavailable.values, { ref: '', sn: '', marque: '' });
		assert.equal(unavailable.simulated, false);
	});

	it('rouvre l\'écran quand la validation part sans référence ni n° de série', () => {
		const retry = validationRetry(new URLSearchParams('marque=MHI&ref=&sn='));
		assert.equal(retry?.check.status, 'retry');
		assert.deepEqual(retry?.check.values, { ref: '', sn: '', marque: 'MHI' });
		assert.deepEqual(retry?.issues, { ref: FIELD_MESSAGES.required });
		assert.equal(validationRetry(new URLSearchParams('ref=SRK25ZS-W&marque=MHI')), null);
		assert.equal(validationRetry(new URLSearchParams('sn=YK19KAZ35S004567&ref=')), null);
		assert.equal(validationRetry(new URLSearchParams('q=SRK25ZS-W')), null);
		assert.equal(validationRetry(new URLSearchParams('')), null);
	});

	it('mène la validation à une URL sans champ vide', () => {
		assert.equal(assistanceUrl({ ref: 'SRK25ZS-W', sn: '', marque: 'MHI' }), '/assistance?ref=SRK25ZS-W&marque=MHI');
		assert.deepEqual(validateFields({ ref: '', sn: '', marque: 'MHI' }), { ref: FIELD_MESSAGES.required });
	});

	it('propose des plaques de démonstration présentes dans public/yack/demo/plaques/', () => {
		for (const plate of PLATE_DEMOS) {
			assert.equal(existsSync(new URL(`../../public${demoPlateUrl(plate.name)}`, import.meta.url)), true, plate.name);
		}
	});
});

describe('question aux manuels indexés', () => {
	const SERIE = { ...DOCUMENT, id: 'serie-texte', title: 'Technical manual SRK20ZS-WF', indexedPages: '45, 82-83' };
	const SANS_TEXTE = { ...DOCUMENT, id: 'sans-texte', hasText: false };
	const search = (extra: Record<string, unknown> = {}) =>
		toManualSearch({
			reference: { read: 'SRK25ZS-W', canonical: 'SRK25ZS-W', structured: true, corrected: false, brand: 'MHI', brandSource: 'catalogue' },
			level: 'exact',
			groups: [
				{ level: 'exact', reason: 'reference_identique', source: 'reference', documents: [DOCUMENT, SANS_TEXTE] },
				{ level: 'serie', reason: 'variante_suffixe', source: 'reference', documents: [SERIE, DOCUMENT] },
				{ level: 'famille', reason: 'meme_type_appareil', source: 'reference', documents: [{ ...DOCUMENT, id: 'famille-texte' }] }
			],
			askable: true,
			...extra
		});

	it('interroge la référence comprise', () => {
		assert.deepEqual(questionTarget(search()), { reference: 'SRK25ZS-W', source: 'reference' });
	});

	it("interroge le premier produit fini d'un n° de série connu, sans référence", () => {
		const bySerial = search({
			reference: null,
			serial: { value: 'YK19KAZ35S004567', known: true, savUrl: '/sav/YK19KAZ35S004567', productCodes: ['SRK25ZS-WFT', 'SRC25ZS-W2'], conflict: false }
		});
		assert.deepEqual(questionTarget(bySerial), { reference: 'SRK25ZS-WFT', source: 'serial' });
		assert.equal(questionTarget(search({ reference: null, serial: { value: 'ABC', known: false, productCodes: [] } })), null);
	});

	it("lit les groupes du n° de série quand la référence saisie est l'un de ses produits (sans conflit)", () => {
		const serial = { value: 'YK19KAZ35S004567', known: true, savUrl: '/sav/YK19KAZ35S004567', productCodes: ['SRK25ZS-WFT', 'SRC25ZS-W2'] };
		const reference = { read: 'SRK25ZS-WFT', canonical: 'SRK25ZS-WFT', structured: true, corrected: false, brand: 'MHI', brandSource: 'catalogue' };
		const groups = [{ level: 'exact', reason: 'reference_identique', source: 'serial', documents: [DOCUMENT] }];
		const sameModel = search({ reference, serial: { ...serial, conflict: false }, groups });
		assert.deepEqual(questionTarget(sameModel), { reference: 'SRK25ZS-WFT', source: 'serial' });
		assert.deepEqual(indexedManuals(sameModel, 'serial'), [{ id: DOCUMENT.id, title: DOCUMENT.title, pages: '12-19' }]);
		assert.deepEqual(questionTarget(search({ serial: { ...serial, conflict: true } })), { reference: 'SRK25ZS-W', source: 'reference' });
		assert.equal(questionTarget(search({ reference: { canonical: 'X'.repeat(REFERENCE_MAX + 1) } })), null);
	});

	it('liste les manuels à texte des groupes exact et série de la source, sans doublon ni famille', () => {
		assert.deepEqual(indexedManuals(search(), 'reference'), [
			{ id: DOCUMENT.id, title: DOCUMENT.title, pages: '12-19' },
			{ id: 'serie-texte', title: 'Technical manual SRK20ZS-WF', pages: '45, 82-83' }
		]);
		assert.deepEqual(indexedManuals(search(), 'serial'), []);
	});

	it('écrit les pages indexées en clair', () => {
		assert.equal(indexedPagesLabel('4-8, 10, 33-38'), '4 à 8, 10, 33 à 38');
		assert.equal(indexedPagesLabel('12 - 19'), '12 à 19');
		assert.equal(indexedPagesLabel('x, 3'), '3');
		assert.equal(indexedPagesLabel(null), '');
	});

	it('ramène le visiteur sur la même URL après connexion', () => {
		assert.equal(questionLoginUrl('/assistance', '?q=SRK25ZS-W'), '/connexion?redirect=%2Fassistance%3Fq%3DSRK25ZS-W');
	});

	it('lit une réponse et lie chaque source au PDF à sa page', () => {
		const answer = toManualAnswer({
			answer: ' Le code E6 signale un défaut de sonde. ',
			sources: [{ documentId: DOCUMENT.id, title: DOCUMENT.title, page: 47, url: DOCUMENT.url }],
			simulated: true
		});
		assert.deepEqual(answer, {
			answer: 'Le code E6 signale un défaut de sonde.',
			sources: [{ documentId: DOCUMENT.id, title: DOCUMENT.title, page: 47, url: 'https://example.org/manuels/rjg012a031.pdf#page=47' }],
			simulated: true
		});
		assert.equal(answerSourceLabel(answer.sources[0]!), `Source : ${DOCUMENT.title}, page 47`);
	});

	it('écarte une source mal formée, hors https ou en double', () => {
		const answer = toManualAnswer({
			answer: 'Réponse.',
			sources: [
				{ documentId: 'a', title: 'A', page: 3, url: 'https://e.org/a.pdf#page=1' },
				{ documentId: 'a', title: 'A', page: 3, url: 'https://e.org/a.pdf' },
				{ documentId: 'b', title: 'B', page: 2, url: 'javascript:alert(1)' },
				{ documentId: 'c', title: 'C', page: 0, url: 'https://e.org/c.pdf' },
				{ documentId: 'd', title: 'D', page: 2.5, url: 'https://e.org/d.pdf' },
				{ documentId: '', title: 'E', page: 1, url: 'https://e.org/e.pdf' },
				'texte'
			]
		});
		assert.deepEqual(answer.sources, [{ documentId: 'a', title: 'A', page: 3, url: 'https://e.org/a.pdf#page=3' }]);
		assert.equal(answer.simulated, false);
	});

	it('dit « je ne trouve pas » sans texte ou quand aucune source de la réponse n\'est gardée', () => {
		const notFound = { answer: QUESTION_MESSAGES.notFound, sources: [], simulated: false };
		assert.deepEqual(toManualAnswer({ answer: 'Réponse.', sources: [{ documentId: 'b', title: 'B', page: 2, url: 'http://e.org/b.pdf' }] }), notFound);
		assert.deepEqual(toManualAnswer({ answer: '', sources: [{ documentId: 'a', title: 'A', page: 3, url: 'https://e.org/a.pdf' }] }), notFound);
		assert.deepEqual(toManualAnswer(null), notFound);
	});

	it("garde le texte d'une réponse rendue sans source par l'API (je ne trouve pas, aucun manuel indexé)", () => {
		const notIndexed = 'Je ne trouve pas la réponse : aucun manuel de ce modèle n\'est indexé pour les questions.';
		assert.deepEqual(toManualAnswer({ answer: notIndexed, sources: [], simulated: false }), { answer: notIndexed, sources: [], simulated: false });
		assert.equal(toManualAnswer({ answer: QUESTION_MESSAGES.notFound, sources: [], simulated: true }).simulated, true);
	});

	it('garde le texte de la réponse tel quel, balises comprises (affiché en texte)', () => {
		const answer = toManualAnswer({ answer: 'Voir <b>E6</b>', sources: [{ documentId: 'a', title: 'A', page: 1, url: 'https://e.org/a.pdf' }] });
		assert.equal(answer.answer, 'Voir <b>E6</b>');
	});
});
