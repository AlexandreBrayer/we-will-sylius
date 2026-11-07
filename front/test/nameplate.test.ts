// Tests of the photo of the rating plate (src/lib/sav/nameplate.ts, IA-05): `npm test`.
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { describe, it } from 'node:test';
import { DEMO_UNKNOWN_SERIAL, NAMEPLATE_MESSAGES, contactUrl, demoPlateUrl, nameplateOutcome, unavailableNotice } from '../src/lib/sav/nameplate.ts';

describe('nameplateOutcome', () => {
	it('opens the appliance page when found', () => {
		assert.deepEqual(nameplateOutcome({ status: 'found', serialNumber: 'YK24HYD11S000321', model: 'Hydra Split 11 kW', simulated: true }), {
			found: true,
			serialNumber: 'YK24HYD11S000321'
		});
	});

	it('says « Appareil non trouvé » with a contact link for an unknown serial', () => {
		const outcome = nameplateOutcome({ status: 'unknown', serialNumber: 'YK23EVO06M009999', model: null, simulated: true });
		assert.equal(outcome.found, false);
		if (outcome.found) return;
		assert.equal(outcome.message, 'Appareil non trouvé');
		assert.equal(outcome.serialNumber, 'YK23EVO06M009999');
		assert.equal(outcome.contactUrl, '/contact?sujet=SAV%20%3A%20appareil%20non%20trouv%C3%A9%20(YK23EVO06M009999)');
		assert.equal(outcome.simulated, true);
	});

	it('asks for the serial number when the plate is unreadable', () => {
		const outcome = nameplateOutcome({ status: 'unreadable', serialNumber: null, model: null, simulated: false });
		assert.equal(outcome.found, false);
		if (outcome.found) return;
		assert.equal(outcome.message, 'Plaque illisible : saisissez le numéro de série');
		assert.equal(outcome.contactUrl, null);
		assert.equal(outcome.simulated, false);
	});

	it('never opens a page without a serial number', () => {
		assert.equal(nameplateOutcome({ status: 'found', serialNumber: '  ', model: null, simulated: true }).found, false);
		const outcome = nameplateOutcome({ status: 'unknown', serialNumber: null, model: null, simulated: true });
		assert.equal(outcome.found ? null : outcome.status, 'unreadable');
	});
});

describe('unavailableNotice', () => {
	it('asks for the serial number, not simulated', () => {
		assert.deepEqual(unavailableNotice(), {
			status: 'unavailable',
			message: NAMEPLATE_MESSAGES.unavailable,
			serialNumber: null,
			model: null,
			simulated: false,
			contactUrl: null
		});
	});
});

describe('demo plates', () => {
	it('points to the FICTIF files of public/yack/demo/plaques/', () => {
		assert.equal(demoPlateUrl('YK24HYD11S000321'), '/yack/demo/plaques/plaque-YK24HYD11S000321-FICTIF.png');
		assert.equal(contactUrl('YK1').startsWith('/contact?sujet='), true);
	});

	it('exist for the demo serials (DEMO_SERIALS of #lib/sav/types.ts) and the unknown one', () => {
		for (const serial of ['YK25EVO08M001234', 'YK19KAZ35S004567', 'YK24HYD11S000321', 'YK22THD200000789', DEMO_UNKNOWN_SERIAL]) {
			assert.equal(existsSync(new URL(`../../public${demoPlateUrl(serial)}`, import.meta.url)), true, serial);
		}
	});
});
