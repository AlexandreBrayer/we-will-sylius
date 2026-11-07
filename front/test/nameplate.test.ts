// Tests of the photo of the rating plate (src/lib/sav/nameplate.ts): `npm test`.
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
	DEMO_PLATES,
	NAMEPLATE_MAX_BYTES,
	NAMEPLATE_MESSAGES,
	PHOTO_MESSAGES,
	contactUrl,
	demoPlateUrl,
	formatBytes,
	jpegName,
	nameplateOutcome,
	needsReduction,
	photoIssue,
	photoKind,
	reducedSize,
	unavailableNotice,
	type NameplateReading
} from '../src/lib/sav/nameplate.ts';
import { UPLOAD_MAX_BYTES } from '../src/lib/server/upload.ts';

const reading = (answer: Partial<NameplateReading> & Pick<NameplateReading, 'status'>): NameplateReading => ({
	serialNumber: null,
	reference: null,
	model: null,
	product: null,
	simulated: true,
	...answer
});

const SRK25 = {
	code: 'SRK25ZS-W',
	name: 'SRK25ZS-W Mural - 2,5kW - R32',
	slug: 'srk25zs-w-mural-2-5kw-r32-srk25zs-w',
	brand: 'MHI',
	documents: ['Documentation Produit', 'Notice diagnostic']
};

describe('nameplateOutcome', () => {
	it('opens the appliance page when found', () => {
		assert.deepEqual(nameplateOutcome(reading({ status: 'found', serialNumber: 'YK24HYD11S000321', reference: 'FDCW-VNX-A', model: 'Hydrolution' })), {
			found: true,
			serialNumber: 'YK24HYD11S000321'
		});
	});

	it('proposes the product sheet and its documents for a catalog reference', () => {
		const outcome = nameplateOutcome(reading({ status: 'product', serialNumber: 'MH21ZSW0004242', reference: 'SRK25ZS-W', product: SRK25 }));
		assert.equal(outcome.found, false);
		if (outcome.found) return;
		assert.equal(outcome.status, 'product');
		assert.equal(outcome.message, 'Modèle reconnu dans le catalogue');
		assert.equal(outcome.product?.url, '/produit/srk25zs-w-mural-2-5kw-r32-srk25zs-w');
		assert.deepEqual(outcome.product?.documents, ['Documentation Produit', 'Notice diagnostic']);
		assert.equal(outcome.serialNumber, 'MH21ZSW0004242');
		assert.equal(outcome.contactUrl, null);
	});

	it('says « Appareil non trouvé » with a contact link for an unknown serial', () => {
		const outcome = nameplateOutcome(reading({ status: 'unknown', serialNumber: 'YK23EVO06M009999', reference: 'YK-PAE-M06' }));
		assert.equal(outcome.found, false);
		if (outcome.found) return;
		assert.equal(outcome.message, 'Appareil non trouvé');
		assert.equal(outcome.serialNumber, 'YK23EVO06M009999');
		assert.equal(outcome.reference, 'YK-PAE-M06');
		assert.equal(outcome.contactUrl, '/contact?sujet=SAV%20%3A%20appareil%20non%20trouv%C3%A9%20(YK23EVO06M009999)');
		assert.equal(outcome.simulated, true);
	});

	it('names the reference in the contact subject when no serial was read', () => {
		const outcome = nameplateOutcome(reading({ status: 'unknown', reference: 'SRK99ZZ-X' }));
		assert.equal(outcome.found ? null : outcome.contactUrl, contactUrl('SRK99ZZ-X'));
	});

	it('asks for the serial number when the plate is unreadable', () => {
		const outcome = nameplateOutcome(reading({ status: 'unreadable', simulated: false }));
		assert.equal(outcome.found, false);
		if (outcome.found) return;
		assert.equal(outcome.message, 'Plaque illisible : saisissez le numéro de série');
		assert.equal(outcome.contactUrl, null);
		assert.equal(outcome.simulated, false);
	});

	it('never opens a page without a serial number nor a sheet without a slug', () => {
		assert.equal(nameplateOutcome(reading({ status: 'found', serialNumber: '  ' })).found, false);
		const unknown = nameplateOutcome(reading({ status: 'unknown' }));
		assert.equal(unknown.found ? null : unknown.status, 'unreadable');
		const product = nameplateOutcome(reading({ status: 'product', reference: 'SRK25ZS-W', product: null }));
		assert.equal(product.found ? null : product.status, 'unreadable');
	});
});

describe('unavailableNotice', () => {
	it('asks for the serial number, not simulated', () => {
		assert.deepEqual(unavailableNotice(), {
			status: 'unavailable',
			message: NAMEPLATE_MESSAGES.unavailable,
			serialNumber: null,
			reference: null,
			model: null,
			product: null,
			simulated: false,
			contactUrl: null
		});
	});
});

describe('the photo', () => {
	it('sorts what the API reads, the iPhone HEIC and the rest', () => {
		assert.equal(photoKind({ name: 'image.jpg', type: 'image/jpeg' }), 'accepted');
		assert.equal(photoKind({ name: 'plaque.PNG', type: '' }), 'accepted');
		assert.equal(photoKind({ name: '1699999999999', type: 'image/webp' }), 'accepted');
		assert.equal(photoKind({ name: 'IMG_0001.HEIC', type: '' }), 'heic');
		assert.equal(photoKind({ name: 'IMG_0001.jpg', type: 'image/heif' }), 'heic');
		assert.equal(photoKind({ name: 'plaque.gif', type: 'image/gif' }), 'other');
		assert.equal(photoKind({ name: 'notice.pdf', type: 'application/pdf' }), 'other');
	});

	it('gives the reason a file cannot be sent as is', () => {
		assert.equal(photoIssue({ name: 'a.jpg', type: 'image/jpeg', size: 0 }), PHOTO_MESSAGES.empty);
		assert.equal(photoIssue({ name: 'IMG_0001.HEIC', type: 'image/heic', size: 10 }), 'Photo HEIC non lue : envoyez-la en JPEG, PNG ou WebP.');
		assert.equal(photoIssue({ name: 'plaque.gif', type: 'image/gif', size: 10 }), 'Format non accepté (JPEG, PNG ou WebP).');
		assert.equal(photoIssue({ name: 'a.jpg', type: 'image/jpeg', size: NAMEPLATE_MAX_BYTES + 1 }), 'Fichier trop volumineux (20 Mo maximum)');
		assert.equal(photoIssue({ name: 'a.jpg', type: 'image/jpeg', size: NAMEPLATE_MAX_BYTES }), null);
	});

	it('has the limit of the upload check and of the API (20 Mo)', () => {
		assert.equal(NAMEPLATE_MAX_BYTES, UPLOAD_MAX_BYTES);
		assert.equal(NAMEPLATE_MAX_BYTES, 20 * 1024 * 1024);
	});

	it('is reduced to 1600 px on its longest side, never enlarged', () => {
		assert.deepEqual(reducedSize(4032, 3024), { width: 1600, height: 1200 });
		assert.deepEqual(reducedSize(3024, 4032), { width: 1200, height: 1600 });
		assert.deepEqual(reducedSize(960, 600), { width: 960, height: 600 });
	});

	it('keeps a light demo plate as is (its PNG marker stays) and reduces phone photos', () => {
		assert.equal(needsReduction({ name: 'plaque-SRK25ZS-W-FICTIF.png', type: 'image/png', size: 9000 }, 960, 600), false);
		assert.equal(needsReduction({ name: 'IMG_0001.jpg', type: 'image/jpeg', size: 3_500_000 }, 4032, 3024), true);
		assert.equal(needsReduction({ name: 'IMG_0001.jpg', type: 'image/jpeg', size: 3_500_000 }, 1500, 1000), true);
		assert.equal(needsReduction({ name: 'IMG_0001.HEIC', type: 'image/heic', size: 900_000 }, 1200, 900), true);
	});

	it('is renamed in .jpg once reduced', () => {
		assert.equal(jpegName('IMG_0001.HEIC'), 'IMG_0001.jpg');
		assert.equal(jpegName('plaque.salon.png'), 'plaque.salon.jpg');
		assert.equal(jpegName(''), 'photo.jpg');
	});

	it('shows its weight in French', () => {
		assert.equal(formatBytes(320 * 1024), '320 Ko');
		assert.equal(formatBytes(Math.round(4.2 * 1024 * 1024)), '4,2 Mo');
	});
});

describe('demo plates', () => {
	it('cover the four cases and exist in public/yack/demo/plaques/ (bin/yack-demo-plaques.php)', () => {
		assert.deepEqual(
			DEMO_PLATES.map((plate) => plate.name),
			['YK24HYD11S000321', 'SRK25ZS-W', 'YK23EVO06M009999', 'illisible']
		);
		for (const name of [...DEMO_PLATES.map((plate) => plate.name), 'YK25EVO08M001234', 'YK19KAZ35S004567', 'YK22THD200000789']) {
			assert.equal(existsSync(new URL(`../../public${demoPlateUrl(name)}`, import.meta.url)), true, name);
		}
	});
});
