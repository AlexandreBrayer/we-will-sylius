// Media library of the builder (src/lib/editor/media/media.ts, D7): the rules of a file sent and their
// messages are the ones of the API (CmsMediaFile, read as a file: no copy can drift), the reading of the
// answers of GET /cms-media and what the window shows. `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { batchProgress, formatBytes, formatDimensions, MEDIA_ALT_MAX, MEDIA_MAX_BYTES, MEDIA_MESSAGES, mediaIssue, parseMediaItem, parseMediaList } from '../src/lib/editor/media/media.ts';

const root = new URL('../../', import.meta.url);
const MEDIA_FILE = readFileSync(new URL('src/Yack/Content/Domain/CmsMediaFile.php', root), 'utf8');
const REFUSALS = readFileSync(new URL('src/Yack/Content/Domain/CmsMediaRefused.php', root), 'utf8').replace('au plus %d caractères', `au plus ${MEDIA_ALT_MAX} caractères`);
const MO = 1024 * 1024;

describe('mediaIssue', () => {
	it('lets a JPEG, PNG or WebP of 20 Mo at most go to the API', () => {
		assert.equal(mediaIssue({ name: 'Photo chantier.JPG', type: 'image/jpeg', size: 1200 }), null);
		assert.equal(mediaIssue({ name: 'a.png', type: '', size: 10 }), null);
		assert.equal(mediaIssue({ name: '1699999999', type: 'image/webp', size: 10 }), null);
		assert.equal(mediaIssue({ name: 'a.jpg', type: 'image/jpeg', size: MEDIA_MAX_BYTES }), null);
	});

	it('refuses with the messages of the API', () => {
		assert.equal(mediaIssue({ name: 'a.jpg', type: 'image/jpeg', size: MEDIA_MAX_BYTES + 1 }), MEDIA_MESSAGES.tooLarge);
		assert.equal(mediaIssue({ name: 'a.jpg', type: 'image/jpeg', size: 0 }), MEDIA_MESSAGES.empty);
		assert.equal(mediaIssue({ name: 'logo.svg', type: 'image/svg+xml', size: 10 }), MEDIA_MESSAGES.svg);
		assert.equal(mediaIssue({ name: 'logo.SVG', type: '', size: 10 }), MEDIA_MESSAGES.svg);
		assert.equal(mediaIssue({ name: 'notice.pdf', type: 'application/pdf', size: 10 }), MEDIA_MESSAGES.format);
		assert.equal(mediaIssue({ name: 'anim.gif', type: 'image/gif', size: 10 }), MEDIA_MESSAGES.format);
		assert.equal(mediaIssue({ name: 'IMG_0001.HEIC', type: 'image/heic', size: 10 }), MEDIA_MESSAGES.format);
	});

	it('uses the limits of CmsMediaFile and the French messages of CmsMediaRefused', () => {
		assert.match(MEDIA_FILE, /MAX_BYTES = 20 \* 1024 \* 1024;/);
		assert.equal(MEDIA_MAX_BYTES, 20 * MO);
		assert.match(MEDIA_FILE, new RegExp(`ALT_MAX = ${MEDIA_ALT_MAX};`));
		for (const message of Object.values(MEDIA_MESSAGES)) assert.ok(REFUSALS.includes(`'${message}'`), message);
	});
});

describe('answers of GET /cms-media', () => {
	const uploaded = {
		id: 22,
		path: 'media/yack-cms/0123456789abcdef0123456789abcdef.jpg',
		url: '/media/yack-cms/0123456789abcdef0123456789abcdef.jpg',
		thumbnail: '/media/cache/resolve/yack_cms_media_thumb/media/yack-cms/0123456789abcdef0123456789abcdef.jpg',
		name: 'Photo chantier.jpg',
		mediaType: 'image/jpeg',
		width: 1920,
		height: 1080,
		size: 1258291,
		alt: 'Chantier de Marseille',
		author: 'Admin Démo',
		createdAt: '2026-11-08T19:12:00+01:00',
		uploaded: true
	};

	it('reads an image as the API sends it', () => {
		assert.deepEqual(parseMediaItem(uploaded), uploaded);
	});

	it('reads a historical visual (no author, not uploaded)', () => {
		const item = parseMediaItem({ ...uploaded, id: 1, path: 'yack/cms/hero.jpg', author: null, uploaded: false, thumbnail: undefined, url: undefined });
		assert.equal(item?.author, null);
		assert.equal(item?.uploaded, false);
		assert.equal(item?.url, '/yack/cms/hero.jpg');
		assert.equal(item?.thumbnail, '/yack/cms/hero.jpg');
	});

	it('leaves out what is not an image', () => {
		assert.deepEqual(parseMediaList([uploaded, null, 'x', { id: 'a', path: 'x' }, { id: 3 }]).map((item) => item.id), [22]);
		assert.deepEqual(parseMediaList({ 'hydra:member': [] }), []);
		assert.equal(parseMediaItem({ ...uploaded, width: -1, size: 'x' })?.width, 0);
	});
});

describe('what the window shows', () => {
	it('writes weights and dimensions in French', () => {
		assert.equal(formatBytes(900), '900 octets');
		assert.equal(formatBytes(845 * 1024), '845 Ko');
		assert.equal(formatBytes(1258291), '1,2 Mo');
		assert.equal(formatBytes(20 * MO), '20 Mo');
		assert.equal(formatDimensions({ width: 1920, height: 1080 }).replace(/\s/g, ' '), '1 920 × 1 080 px');
	});

	it('measures the progress of a batch by the bytes of the files done', () => {
		assert.equal(batchProgress([]), 1);
		assert.equal(batchProgress([{ size: 3 * MO, done: true }, { size: MO, done: false }]), 0.75);
		assert.equal(batchProgress([{ size: 0, done: true }, { size: 0, done: true }]), 1);
	});
});
