// Tests of the edit lock of the builder (src/lib/editor/lock.ts and the shortcut rule of lockCanvas.ts):
// reading of the lock API, what the editor becomes after each answer (editing, read-only and why), the
// French texts of the banner in the local time of the browser, the session (signs of life every
// `heartbeatSeconds`, take over, release) with fake timers. Same timings as Content\Domain\CmsEditLockState
// (read as a file). `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
	createLockSession,
	INITIAL_VIEW,
	isReadOnly,
	lockNotice,
	lockTimeLabel,
	nextPhase,
	parseEditLock,
	takeoverQuestion,
	type LockAction,
	type LockView
} from '../src/lib/editor/lock.ts';
import { isEditShortcut } from '../src/lib/editor/lockCanvas.ts';

const root = new URL('../../', import.meta.url);
const read = (path: string) => readFileSync(new URL(path, root), 'utf8');
const TOKEN = 'a'.repeat(32);
const OTHER_TOKEN = 'b'.repeat(32);
const at = (h: number, m: number) => new Date(2026, 10, 8, h, m).toISOString();
const NOW = new Date(2026, 10, 8, 16, 30);

/** Answer of the API as Sylius sends it. */
const answer = (state: 'free' | 'mine' | 'other', overrides: Record<string, unknown> = {}) => ({
	target: 'page',
	targetId: 'offre',
	state,
	holder: state === 'free' ? null : { firstName: 'Sophie', lastName: 'Martin', name: 'Sophie Martin' },
	since: state === 'free' ? null : at(14, 5),
	heartbeatAt: null,
	expiresAt: null,
	token: state === 'mine' ? TOKEN : null,
	now: at(14, 6),
	heartbeatSeconds: 30,
	timeoutSeconds: 120,
	...overrides
});
const lock = (state: 'free' | 'mine' | 'other', overrides: Record<string, unknown> = {}) => parseEditLock(answer(state, overrides));
const view = (phase: LockView['phase'], extra: Partial<LockView> = {}): LockView => ({ ...INITIAL_VIEW, phase, ...extra });

describe('parseEditLock', () => {
	it('reads the three states', () => {
		assert.deepEqual(lock('mine'), { state: 'mine', holder: 'Sophie Martin', since: at(14, 5), token: TOKEN, heartbeatSeconds: 30, timeoutSeconds: 120 });
		assert.equal(lock('other')?.token, null);
		assert.deepEqual(lock('free'), { state: 'free', holder: null, since: null, token: null, heartbeatSeconds: 30, timeoutSeconds: 120 });
	});
	it('refuses what is not a lock', () => {
		for (const raw of [null, 'x', [], { state: 'stolen' }, answer('other', { holder: null }), answer('mine', { holder: { name: ' ' } })]) assert.equal(parseEditLock(raw), null);
	});
	it('keeps only a well formed token, and only for its holder', () => {
		assert.equal(lock('mine', { token: 'abc' })?.token, null);
		assert.equal(lock('other', { token: TOKEN })?.token, null);
	});
	it('falls back to the timings of Sylius when they are absent or absurd', () => {
		assert.equal(lock('mine', { heartbeatSeconds: 0, timeoutSeconds: 'x' })?.heartbeatSeconds, 30);
		assert.equal(lock('mine', { timeoutSeconds: 1e9 })?.timeoutSeconds, 120);
		assert.equal(lock('mine', { heartbeatSeconds: 10 })?.heartbeatSeconds, 10);
	});
	it('uses the timings of CmsEditLockState', () => {
		const php = read('src/Yack/Content/Domain/CmsEditLockState.php');
		assert.match(php, /const int HEARTBEAT = 30;/);
		assert.match(php, /const int TIMEOUT = 120;/);
	});
});

describe('nextPhase', () => {
	it('opening: the lock is ours, or someone else edits', () => {
		assert.deepEqual(nextPhase(INITIAL_VIEW, lock('mine')), { phase: 'editing', holder: 'Sophie Martin', since: at(14, 5), token: TOKEN });
		assert.deepEqual(nextPhase(INITIAL_VIEW, lock('other')), { phase: 'blocked', holder: 'Sophie Martin', since: at(14, 5), token: null });
	});
	it('opening without an answer: editable, with a warning, tried again', () => {
		assert.equal(nextPhase(INITIAL_VIEW, null).phase, 'unavailable');
		assert.equal(nextPhase(view('unavailable'), lock('mine')).phase, 'editing');
		assert.equal(nextPhase(view('unavailable'), lock('other')).phase, 'blocked');
		assert.equal(nextPhase(INITIAL_VIEW, lock('free')).phase, 'unavailable');
	});
	it('a failed sign of life changes nothing', () => {
		const editing = view('editing', { token: TOKEN });
		assert.equal(nextPhase(editing, null), editing);
	});
	it('editing: taken over by another administrator', () => {
		const next = nextPhase(view('editing', { token: TOKEN }), lock('other', { since: at(14, 20) }));
		assert.deepEqual(next, { phase: 'taken', holder: 'Sophie Martin', since: at(14, 20), token: null });
		assert.ok(isReadOnly(next));
	});
	it('editing: the lock changed hands meanwhile (free, or ours with another token)', () => {
		assert.equal(nextPhase(view('editing', { token: TOKEN }), lock('free')).phase, 'lost');
		assert.equal(nextPhase(view('editing', { token: TOKEN }), lock('mine', { token: OTHER_TOKEN })).phase, 'lost');
		assert.equal(nextPhase(view('editing', { token: TOKEN }), lock('mine')).phase, 'editing');
	});
	it('read-only: the holder leaves, or another one comes', () => {
		assert.equal(nextPhase(view('blocked', { holder: 'Sophie Martin' }), lock('free')).phase, 'freed');
		assert.equal(nextPhase(view('blocked', { holder: 'Sophie Martin' }), lock('free')).holder, 'Sophie Martin');
		assert.equal(nextPhase(view('taken'), lock('free')).phase, 'freed');
		assert.equal(nextPhase(view('freed'), lock('other')).phase, 'blocked');
		assert.equal(nextPhase(view('taken'), lock('other')).phase, 'taken');
	});
	it('a read-only editor never becomes editable by itself: its canvas is not the saved version', () => {
		for (const phase of ['blocked', 'taken', 'lost', 'freed'] as const) {
			assert.ok(isReadOnly(nextPhase(view(phase), lock('mine'))), phase);
			assert.ok(isReadOnly(nextPhase(view(phase), lock('free'))), phase);
		}
		assert.equal(nextPhase(view('lost'), lock('other')).phase, 'lost');
	});
});

describe('texts', () => {
	it('« 14 h 05 » today, the day otherwise, in the time of the browser', () => {
		assert.equal(lockTimeLabel(at(14, 5), NOW), '14 h 05');
		assert.equal(lockTimeLabel(new Date(2026, 10, 7, 23, 50).toISOString(), NOW), '7 novembre, 23 h 50');
		assert.equal(lockTimeLabel(null, NOW), '');
		assert.equal(lockTimeLabel('pas une date', NOW), '');
	});
	it('the second administrator reads who edits since when, and can take over', () => {
		const notice = lockNotice(view('blocked', { holder: 'Sophie Martin', since: at(14, 5) }), 'page', false, NOW);
		assert.equal(notice?.title, 'Sophie Martin modifie cette page depuis 14 h 05.');
		assert.match(notice?.detail ?? '', /^Lecture seule/);
		assert.equal(notice?.action, 'takeover');
		assert.equal(notice?.actionLabel, 'Prendre la main');
		assert.equal(lockNotice(view('blocked', { holder: 'Sophie Martin', since: at(14, 5) }), 'template', false, NOW)?.title, 'Sophie Martin modifie ce template depuis 14 h 05.');
	});
	it('the first administrator reads who took over, and what becomes of his work', () => {
		const saved = lockNotice(view('taken', { holder: 'Paul Durand', since: at(14, 20) }), 'page', false, NOW);
		assert.equal(saved?.title, 'Paul Durand a pris la main à 14 h 20.');
		assert.equal(saved?.detail, 'Ce que vous aviez enregistré est conservé ; cette page est maintenant en lecture seule pour vous.');
		const unsaved = lockNotice(view('taken', { holder: 'Paul Durand', since: at(14, 20) }), 'page', true, NOW);
		assert.match(unsaved?.detail ?? '', /Vos modifications non enregistrées restent affichées ici, en lecture seule, jusqu’au rechargement de la page/);
		assert.equal(unsaved?.actionLabel, 'Reprendre la main');
	});
	it('lost and freed ask for a reload; unavailable only warns; editing says nothing', () => {
		assert.equal(lockNotice(view('lost'), 'page', false, NOW)?.action, 'reload');
		const freed = lockNotice(view('freed', { holder: 'Sophie Martin' }), 'template', false, NOW);
		assert.equal(freed?.title, 'Sophie Martin ne modifie plus ce template.');
		assert.equal(freed?.actionLabel, 'Modifier ce template');
		assert.equal(lockNotice(view('unavailable'), 'page', false, NOW)?.action, null);
		assert.equal(lockNotice(view('editing'), 'page', true, NOW), null);
		assert.equal(lockNotice(INITIAL_VIEW, 'page', false, NOW), null);
	});
	it('the confirmation of the take over', () => {
		assert.equal(
			takeoverQuestion(view('blocked', { holder: 'Sophie Martin' }), 'page', false),
			'Prendre la main sur cette page ? Sophie Martin passera en lecture seule et ne pourra plus enregistrer ses modifications en cours. La page se recharge avec la dernière version enregistrée.'
		);
		assert.equal(
			takeoverQuestion(view('taken', { holder: 'Paul Durand' }), 'template', true),
			'Prendre la main sur ce template ? Le template se recharge avec la dernière version enregistrée. Vos modifications non enregistrées seront perdues.'
		);
	});
	it('no long dash nor en dash in the lock files', () => {
		for (const path of ['front/src/lib/editor/lock.ts', 'front/src/lib/editor/lockCanvas.ts', 'front/src/routes/cms-admin/verrou/+server.ts']) assert.doesNotMatch(read(path), /[–—]/, path);
	});
});

describe('isEditShortcut', () => {
	const key = (k: string, extra: Partial<KeyboardEvent> = {}) => ({ key: k, ctrlKey: false, metaKey: false, altKey: false, target: null, ...extra });
	it('holds undo, redo, cut, paste, duplicate, save and delete', () => {
		for (const k of ['z', 'Z', 'y', 'x', 'v', 'd', 's']) {
			assert.ok(isEditShortcut(key(k, { ctrlKey: true })), k);
			assert.ok(isEditShortcut(key(k, { metaKey: true })), k);
		}
		assert.ok(isEditShortcut(key('Delete')));
		assert.ok(isEditShortcut(key('Backspace')));
		assert.ok(isEditShortcut(key('Backspace', { target: { tagName: 'DIV' } as unknown as EventTarget })));
	});
	it('lets Delete and Backspace type in a field', () => {
		assert.ok(!isEditShortcut(key('Backspace', { target: { tagName: 'INPUT' } as unknown as EventTarget })));
		assert.ok(!isEditShortcut(key('Delete', { target: { tagName: 'P', isContentEditable: true } as unknown as EventTarget })));
	});
	it('lets the rest through: reading, copying, moving the focus', () => {
		for (const event of [key('c', { ctrlKey: true }), key('z'), key('Tab'), key('Enter'), key('ArrowDown'), key('z', { ctrlKey: true, altKey: true })]) assert.ok(!isEditShortcut(event), event.key);
	});
});

/** Timers driven by the test. */
function fakeTimers() {
	const handlers = new Map<number, { handler: () => void; ms: number }>();
	let next = 1;
	return {
		handlers,
		setInterval: (handler: () => void, ms: number) => {
			handlers.set(next, { handler, ms });
			return next++;
		},
		clearInterval: (id: unknown) => void handlers.delete(id as number),
		fire: async () => {
			for (const { handler } of [...handlers.values()]) handler();
			await new Promise((resolve) => setTimeout(resolve, 0));
		}
	};
}

/** API of a test: answers in order, records the calls. */
function fakeApi(answers: Array<unknown | Error>) {
	const calls: Array<[LockAction | 'read', string | null]> = [];
	const respond = () => {
		const next = answers.shift();
		return next instanceof Error || next === undefined ? Promise.reject(next ?? new Error('plus de réponse')) : Promise.resolve(next);
	};
	return {
		calls,
		act: (action: LockAction, token: string | null) => {
			calls.push([action, token]);
			return respond();
		},
		read: () => {
			calls.push(['read', null]);
			return respond();
		}
	};
}

const PAGE = { target: 'page', slug: 'offre' } as const;

describe('createLockSession', () => {
	it('takes the lock at the opening, then a sign of life every 30 s with its token', async () => {
		const api = fakeApi([answer('mine'), answer('mine'), answer('mine')]);
		const timers = fakeTimers();
		const views: string[] = [];
		const session = createLockSession(PAGE, api, (v) => views.push(v.phase), timers);

		assert.equal((await session.start()).phase, 'editing');
		assert.deepEqual([...timers.handlers.values()].map((t) => t.ms), [30000]);
		await timers.fire();
		await timers.fire();
		assert.deepEqual(api.calls, [['acquire', null], ['heartbeat', TOKEN], ['heartbeat', TOKEN]]);
		assert.deepEqual(views, ['editing']);
		assert.deepEqual(session.beacon(), { target: PAGE, token: TOKEN });
	});
	it('follows the period given by Sylius', async () => {
		const timers = fakeTimers();
		await createLockSession(PAGE, fakeApi([answer('mine', { heartbeatSeconds: 20 })]), () => {}, timers).start();
		assert.deepEqual([...timers.handlers.values()].map((t) => t.ms), [20000]);
	});
	it('the first administrator learns the take over at his next sign of life', async () => {
		const api = fakeApi([answer('mine'), answer('other', { holder: { name: 'Paul Durand' }, since: at(14, 20) })]);
		const timers = fakeTimers();
		const views: LockView[] = [];
		const session = createLockSession(PAGE, api, (v) => views.push(v), timers);
		await session.start();
		await timers.fire();
		assert.deepEqual(views.at(-1), { phase: 'taken', holder: 'Paul Durand', since: at(14, 20), token: null });
		assert.equal(session.beacon(), null, 'nothing to give back');
	});
	it('a read-only editor reads the lock at each tick, and sees the holder leave', async () => {
		const api = fakeApi([answer('other'), answer('other'), answer('free')]);
		const timers = fakeTimers();
		const session = createLockSession(PAGE, api, () => {}, timers);
		assert.equal((await session.start()).phase, 'blocked');
		await timers.fire();
		await timers.fire();
		assert.deepEqual(api.calls.map(([action]) => action), ['acquire', 'read', 'read']);
		assert.equal(session.view().phase, 'freed');
	});
	it('a failed opening asks again at the next tick; a failed sign of life changes nothing', async () => {
		const api = fakeApi([new Error('réseau'), answer('mine'), new Error('réseau'), answer('mine')]);
		const timers = fakeTimers();
		const session = createLockSession(PAGE, api, () => {}, timers);
		assert.equal((await session.start()).phase, 'unavailable');
		await timers.fire();
		assert.equal(session.view().phase, 'editing');
		await timers.fire();
		assert.equal(session.view().phase, 'editing');
		await timers.fire();
		assert.deepEqual(api.calls.map(([action]) => action), ['acquire', 'acquire', 'heartbeat', 'heartbeat']);
	});
	it('« Prendre la main »: editing at once when Sylius gives the lock', async () => {
		const api = fakeApi([answer('other'), answer('mine', { token: OTHER_TOKEN })]);
		const session = createLockSession(PAGE, api, () => {}, fakeTimers());
		await session.start();
		assert.equal(await session.takeover(), true);
		assert.deepEqual(session.view(), { phase: 'editing', holder: 'Sophie Martin', since: at(14, 5), token: OTHER_TOKEN });
		assert.deepEqual(api.calls.at(-1), ['takeover', null]);
	});
	it('a failed take over leaves the editor read-only', async () => {
		const session = createLockSession(PAGE, fakeApi([answer('other'), new Error('réseau')]), () => {}, fakeTimers());
		await session.start();
		assert.equal(await session.takeover(), false);
		assert.equal(session.view().phase, 'blocked');
	});
	it('release gives the lock back once and stops the signs of life', async () => {
		const api = fakeApi([answer('mine'), answer('free')]);
		const timers = fakeTimers();
		const session = createLockSession(PAGE, api, () => {}, timers);
		await session.start();
		session.release();
		session.release();
		assert.deepEqual(api.calls, [['acquire', null], ['release', TOKEN]]);
		assert.equal(timers.handlers.size, 0);
		assert.equal(await session.takeover(), false, 'a stopped session does nothing');
	});
	it('stop keeps the lock (reload after a take over); a read-only editor gives nothing back', async () => {
		const api = fakeApi([answer('other')]);
		const timers = fakeTimers();
		const session = createLockSession(PAGE, api, () => {}, timers);
		await session.start();
		session.release();
		assert.deepEqual(api.calls, [['acquire', null]]);
		const kept = createLockSession(PAGE, fakeApi([answer('mine')]), () => {}, fakeTimers());
		await kept.start();
		kept.stop();
		assert.equal(kept.view().phase, 'editing');
	});
	it('two ticks at once make one call', async () => {
		const api = fakeApi([answer('mine'), answer('mine')]);
		const session = createLockSession(PAGE, api, () => {}, fakeTimers());
		await session.start();
		await Promise.all([session.check(), session.check()]);
		assert.equal(api.calls.length, 2);
	});
});
