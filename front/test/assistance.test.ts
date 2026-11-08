// Tests du module pur de /assistance (src/lib/assistance/assistance.ts) : `npm test`.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
	FIELD_MESSAGES,
	MANUAL_TYPES,
	MORE_CHOICES_MESSAGE,
	REFERENCE_MAX,
	QUERY_MAX,
	assistanceUrl,
	conflictMessage,
	contactUrl,
	isEmptyInput,
	levelLabel,
	pagesLabel,
	pdfPageUrl,
	productUrl,
	reasonLabel,
	searchForm,
	searchInput,
	sortLanguages,
	sourceLabel,
	toManualSearch,
	typeLabel,
	validateFields
} from '../src/lib/assistance/assistance.ts';

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
	hasText: true
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
