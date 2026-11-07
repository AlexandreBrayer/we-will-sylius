// Tests of the file checks made before the Sylius API call (src/lib/server/upload.ts): `npm test`.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import * as v from 'valibot';
import { UPLOAD_EMPTY, UPLOAD_MAX_BYTES, UPLOAD_TOO_LARGE, extensionOf, uploadField, uploadFormData, uploadIssue } from '../src/lib/server/upload.ts';

const MB = 1024 * 1024;
const KBIS = ['pdf', 'jpg', 'jpeg', 'png'] as const;

/** A file of `size` bytes (zeros): enough for the size and name rules. */
function file(name: string, size: number): File {
	return new File([new Uint8Array(size)], name, { type: 'application/octet-stream' });
}

describe('uploadIssue', () => {
	it('accepts a 15 Mo file with an accepted extension', () => {
		assert.equal(uploadIssue({ name: 'kbis-FICTIF.pdf', size: 15 * MB }, KBIS), null);
		assert.equal(uploadIssue({ name: 'KBIS-FICTIF.PDF', size: 1 }, KBIS), null);
	});

	it('refuses a 25 Mo file with the French message', () => {
		assert.equal(uploadIssue({ name: 'kbis-FICTIF.pdf', size: 25 * MB }, KBIS), 'Fichier trop volumineux (20 Mo maximum)');
	});

	it('takes 20 Mo as the limit, bounds included', () => {
		assert.equal(UPLOAD_MAX_BYTES, 20 * MB);
		assert.equal(uploadIssue({ name: 'a.pdf', size: UPLOAD_MAX_BYTES }, KBIS), null);
		assert.equal(uploadIssue({ name: 'a.pdf', size: UPLOAD_MAX_BYTES + 1 }, KBIS), UPLOAD_TOO_LARGE);
		assert.equal(uploadIssue({ name: 'a.pdf', size: 2 * MB }, KBIS, MB), UPLOAD_TOO_LARGE);
	});

	it('refuses an empty file and an extension that is not accepted', () => {
		assert.equal(uploadIssue({ name: 'a.pdf', size: 0 }, KBIS), UPLOAD_EMPTY);
		assert.equal(uploadIssue({ name: 'kbis.exe', size: 10 }, KBIS), 'Format non accepté (PDF, JPG, JPEG, PNG).');
		assert.equal(uploadIssue({ name: 'kbis', size: 10 }, KBIS), 'Format non accepté (PDF, JPG, JPEG, PNG).');
		assert.equal(uploadIssue({ name: 'kbis.pdf.php', size: 10 }, KBIS), 'Format non accepté (PDF, JPG, JPEG, PNG).');
	});
});

describe('extensionOf', () => {
	it('gives the last extension in lower case, or nothing', () => {
		assert.equal(extensionOf('Kbis.Fictif.PDF'), 'pdf');
		assert.equal(extensionOf('.pdf'), '');
		assert.equal(extensionOf('kbis.'), '');
		assert.equal(extensionOf('kbis'), '');
	});
});

describe('uploadField', () => {
	const schema = uploadField(KBIS);

	it('accepts an allowed file', () => {
		assert.equal(v.safeParse(schema, file('kbis-FICTIF.pdf', 1024)).success, true);
	});

	it('refuses with the same messages as uploadIssue', () => {
		const message = (value: unknown) => v.safeParse(schema, value).issues?.[0]?.message;
		assert.equal(message(file('kbis-FICTIF.pdf', 0)), UPLOAD_EMPTY);
		assert.equal(message(file('kbis.exe', 10)), 'Format non accepté (PDF, JPG, JPEG, PNG).');
		assert.equal(message('kbis.pdf'), 'Fichier obligatoire.');
		assert.equal(v.safeParse(uploadField(KBIS, 10), file('a.pdf', 11)).issues?.[0]?.message, UPLOAD_TOO_LARGE);
	});
});

describe('uploadFormData', () => {
	it('carries the text fields and the files under their field names', () => {
		const kbis = file('kbis-FICTIF.pdf', 3);
		const body = uploadFormData({ siret: '73282932000074' }, { kbis });
		assert.equal(body.get('siret'), '73282932000074');
		const sent = body.get('kbis');
		assert.ok(sent instanceof File);
		assert.equal(sent.name, 'kbis-FICTIF.pdf');
		assert.equal(sent.size, 3);
	});
});
