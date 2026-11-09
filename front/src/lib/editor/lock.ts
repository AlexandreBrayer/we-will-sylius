// Edit lock of the builder (D13): one administrator edits a page or a template, the others read it.
// The lock is taken at the opening, kept alive every 30 s (heartbeatSeconds of the API), free after 2 min
// without a sign of life (timeoutSeconds), given back at the closing. Sylius is the judge: a save over the
// lock of another administrator is refused (409); this module only follows the lock and says it.
//  - parseEditLock(): answer of GET / POST /admin/cms-pages/{slug}/lock (or /cms-templates/{id}/lock);
//  - nextPhase(): what the editor becomes after an answer (editing, read-only and why);
//  - lockNotice(), takeoverQuestion(): the French texts of the banner, local time of the browser;
//  - createLockSession(): the timer of the signs of life, the take over and the release.
// Pure module (no GrapesJS, no Svelte), tested by test/lock.test.ts. Relative `.ts` imports: run by node
// in the tests.
import { dayLabel, timeLabel } from './panels/history.ts';

/** Content of the lock: a page by its slug, a template by its id (same shape as the history). */
export type LockTarget = { target: 'page'; slug: string } | { target: 'template'; id: number };

export type LockAction = 'acquire' | 'heartbeat' | 'takeover' | 'release';

/** Lock as the administrator who asks sees it. */
export interface EditLock {
	state: 'free' | 'mine' | 'other';
	/** « Prénom Nom » (or the e-mail) of the holder, null when free. */
	holder: string | null;
	/** Start of the edit of the holder (ISO 8601), null when free. */
	since: string | null;
	/** Token of the lock, only for its holder. */
	token: string | null;
	heartbeatSeconds: number;
	timeoutSeconds: number;
}

/**
 * What the editor is, for the lock:
 *  - pending: the opening asked for the lock, no answer yet (editable; Sylius judges the saves);
 *  - unavailable: the lock could not be read (editable, with a warning; asked again at each tick);
 *  - editing: the administrator holds the lock;
 *  - blocked: another administrator held it at the opening (read-only, « Prendre la main »);
 *  - taken: another administrator took it over (read-only, « Prendre la main »);
 *  - lost: the lock changed hands while this editor was silent, and is free again (read-only, reload);
 *  - freed: blocked or taken, and the holder left (read-only until a reload loads the saved version).
 */
export type LockPhase = 'pending' | 'unavailable' | 'editing' | 'blocked' | 'taken' | 'lost' | 'freed';

export interface LockView {
	phase: LockPhase;
	/** Holder shown by the banner (the last one known, for `freed`). */
	holder: string | null;
	/** blocked: start of his edit; taken: when he took over. */
	since: string | null;
	/** Token of this editor while it holds the lock. */
	token: string | null;
}

export const READ_ONLY_PHASES: readonly LockPhase[] = ['blocked', 'taken', 'lost', 'freed'];

export const isReadOnly = (view: LockView): boolean => READ_ONLY_PHASES.includes(view.phase);

export const INITIAL_VIEW: LockView = { phase: 'pending', holder: null, since: null, token: null };

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);

const seconds = (value: unknown, fallback: number): number =>
	typeof value === 'number' && Number.isInteger(value) && value >= 5 && value <= 3600 ? value : fallback;

/** Answer of the lock API; null when it is not one. */
export function parseEditLock(raw: unknown): EditLock | null {
	if (!isRecord(raw)) return null;
	const { state, holder, since, token } = raw;
	if (state !== 'free' && state !== 'mine' && state !== 'other') return null;
	const name = isRecord(holder) && typeof holder.name === 'string' && holder.name.trim() !== '' ? holder.name.trim() : null;
	if (state !== 'free' && name === null) return null;
	return {
		state,
		holder: state === 'free' ? null : name,
		since: state !== 'free' && typeof since === 'string' && !Number.isNaN(Date.parse(since)) ? since : null,
		token: state === 'mine' && typeof token === 'string' && /^[0-9a-f]{32}$/.test(token) ? token : null,
		heartbeatSeconds: seconds(raw.heartbeatSeconds, 30),
		timeoutSeconds: seconds(raw.timeoutSeconds, 120)
	};
}

/** The editor after an answer of the API (null: the call failed). */
export function nextPhase(view: LockView, lock: EditLock | null): LockView {
	const { phase } = view;
	if (lock === null) return phase === 'pending' ? { ...INITIAL_VIEW, phase: 'unavailable' } : view;
	const holderView = (next: LockPhase): LockView => ({ phase: next, holder: lock.holder, since: lock.since, token: null });
	switch (lock.state) {
		case 'mine':
			if (phase === 'pending' || phase === 'unavailable') return { phase: 'editing', holder: lock.holder, since: lock.since, token: lock.token };
			if (phase === 'editing') {
				// Another token: the lock left this administrator and came back (another tab): this editor is late.
				return lock.token === view.token ? { ...view, since: lock.since } : { phase: 'lost', holder: null, since: null, token: null };
			}
			// Read-only editors stay so: their canvas is not the saved version.
			return view;
		case 'other':
			if (phase === 'editing' || phase === 'taken') return holderView('taken');
			if (phase === 'lost') return view;
			return holderView('blocked');
		case 'free':
			if (phase === 'editing') return { phase: 'lost', holder: null, since: null, token: null };
			if (phase === 'blocked' || phase === 'taken') return { ...view, phase: 'freed' };
			if (phase === 'pending' || phase === 'unavailable') return { ...INITIAL_VIEW, phase: 'unavailable' };
			return view;
	}
}

/** « 14 h 05 » today, « 7 novembre, 23 h 50 » another day (local time of the browser). */
export function lockTimeLabel(iso: string | null, now: Date = new Date()): string {
	const date = iso === null ? new Date(Number.NaN) : new Date(iso);
	if (Number.isNaN(date.getTime())) return '';
	const today = date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth() && date.getDate() === now.getDate();
	return today ? timeLabel(date) : `${dayLabel(date, now)}, ${timeLabel(date)}`;
}

export type LockKind = 'page' | 'template';

export interface LockNotice {
	title: string;
	detail: string;
	/** takeover: « Prendre la main »; reload: loads the saved version (and takes the lock if free). */
	action: 'takeover' | 'reload' | null;
	actionLabel: string;
}

const THIS: Record<LockKind, string> = { page: 'cette page', template: 'ce template' };
const UNSAVED =
	' Vos modifications non enregistrées restent affichées ici, en lecture seule, jusqu’au rechargement de la page : elles ne peuvent plus être enregistrées. Notez ce que vous voulez garder avant de recharger.';

/** Banner of a read-only editor, or the warning of an editor without lock; null while editing. */
export function lockNotice(view: LockView, kind: LockKind, dirty: boolean, now: Date = new Date()): LockNotice | null {
	const name = view.holder ?? 'Un autre administrateur';
	const time = lockTimeLabel(view.since, now);
	switch (view.phase) {
		case 'blocked':
			return {
				title: time ? `${name} modifie ${THIS[kind]} depuis ${time}.` : `${name} modifie ${THIS[kind]}.`,
				detail: `Lecture seule : vous voyez la dernière version enregistrée et ne pouvez pas la modifier. « Prendre la main » vous confie l’édition ; ${name} passe alors en lecture seule.`,
				action: 'takeover',
				actionLabel: 'Prendre la main'
			};
		case 'taken':
			return {
				title: time ? `${name} a pris la main à ${time}.` : `${name} a pris la main.`,
				detail: `Ce que vous aviez enregistré est conservé ; ${THIS[kind]} est maintenant en lecture seule pour vous.${dirty ? UNSAVED : ''}`,
				action: 'takeover',
				actionLabel: 'Reprendre la main'
			};
		case 'lost':
			return {
				title: `Vous n’avez plus la main sur ${THIS[kind]}.`,
				detail: `Un autre administrateur l’a prise puis rendue pendant que votre éditeur ne répondait plus : il a pu l’enregistrer. Rechargez pour repartir de la dernière version enregistrée.${dirty ? UNSAVED : ''}`,
				action: 'reload',
				actionLabel: 'Recharger'
			};
		case 'freed':
			return {
				title: `${name} ne modifie plus ${THIS[kind]}.`,
				detail: `Rechargez pour la modifier à partir de la dernière version enregistrée.${dirty ? UNSAVED : ''}`,
				action: 'reload',
				actionLabel: kind === 'page' ? 'Modifier cette page' : 'Modifier ce template'
			};
		case 'unavailable':
			return {
				title: 'Le verrou d’édition n’a pas pu être pris.',
				detail: `Un autre administrateur pourrait modifier ${THIS[kind]} en même temps que vous ; Sylius refusera votre enregistrement si quelqu’un d’autre a la main. Nouvel essai dans quelques secondes.`,
				action: null,
				actionLabel: ''
			};
		default:
			return null;
	}
}

/** Confirmation of « Prendre la main ». */
export function takeoverQuestion(view: LockView, kind: LockKind, dirty: boolean): string {
	const name = view.holder ?? 'L’autre administrateur';
	const reload = `${kind === 'page' ? 'La page' : 'Le template'} se recharge avec la dernière version enregistrée.`;
	const holder = view.phase === 'blocked' ? `${name} passera en lecture seule et ne pourra plus enregistrer ses modifications en cours. ` : '';
	return `Prendre la main sur ${THIS[kind]} ? ${holder}${reload}${dirty ? ' Vos modifications non enregistrées seront perdues.' : ''}`;
}

export interface LockApi {
	act(action: LockAction, token: string | null): Promise<unknown>;
	read(): Promise<unknown>;
}

export interface LockTimers {
	setInterval(handler: () => void, ms: number): unknown;
	clearInterval(id: unknown): void;
}

export interface LockSession {
	/** Asks for the lock (opening), then starts the signs of life. */
	start(): Promise<LockView>;
	/** Sign of life now (editing), or read again (read-only): after a 409, at `pageshow`. */
	check(): Promise<LockView>;
	/** « Prendre la main »: true when this administrator holds the lock afterwards. */
	takeover(): Promise<boolean>;
	/** Stops the timer and gives the lock back through the API (closing of the editor, not awaited). */
	release(): void;
	/** Body of the release sent by the browser at `pagehide` (sendBeacon), null when nothing to give back. */
	beacon(): { target: LockTarget; token: string } | null;
	/** Stops the timer without giving the lock back (reload after a take over). */
	stop(): void;
	view(): LockView;
}

/**
 * Lock of one editor: `onchange` receives every new view. Ticks every `heartbeatSeconds` of the API: a sign
 * of life while editing, a read while read-only (to see the holder leave), the acquire again while the lock
 * could not be taken. A failed call changes nothing (the next tick tries again; the lock lives 2 minutes).
 */
export function createLockSession(
	target: LockTarget,
	api: LockApi,
	onchange: (view: LockView) => void,
	timers: LockTimers = { setInterval: (handler, ms) => setInterval(handler, ms), clearInterval: (id) => clearInterval(id as ReturnType<typeof setInterval>) }
): LockSession {
	let view: LockView = INITIAL_VIEW;
	let timer: unknown = null;
	let period = 30;
	let stopped = false;
	let busy: Promise<LockView> | null = null;

	const apply = (lock: EditLock | null): LockView => {
		if (stopped) return view;
		if (lock && lock.heartbeatSeconds !== period) {
			period = lock.heartbeatSeconds;
			if (timer !== null) schedule();
		}
		const next = nextPhase(view, lock);
		if (next.phase !== view.phase || next.holder !== view.holder || next.since !== view.since || next.token !== view.token) {
			view = next;
			onchange(view);
		}
		return view;
	};
	const call = (run: () => Promise<unknown>): Promise<LockView> => run().then((raw) => apply(parseEditLock(raw)), () => apply(null));
	const tick = (): Promise<LockView> => {
		if (stopped) return Promise.resolve(view);
		busy ??= (view.phase === 'editing'
			? call(() => api.act('heartbeat', view.token))
			: view.phase === 'pending' || view.phase === 'unavailable'
				? call(() => api.act('acquire', null))
				: call(() => api.read())
		).finally(() => (busy = null));
		return busy;
	};
	function schedule(): void {
		if (timer !== null) timers.clearInterval(timer);
		timer = timers.setInterval(() => void tick(), period * 1000);
	}
	const stop = (): void => {
		stopped = true;
		if (timer !== null) timers.clearInterval(timer);
		timer = null;
	};

	return {
		async start() {
			const result = await tick();
			if (!stopped) schedule();
			return result;
		},
		check: tick,
		async takeover() {
			if (stopped) return false;
			const lock = await api.act('takeover', null).then(parseEditLock, () => null);
			if (lock?.state !== 'mine') return false;
			view = { phase: 'editing', holder: lock.holder, since: lock.since, token: lock.token };
			onchange(view);
			return true;
		},
		release() {
			const token = !stopped && view.phase === 'editing' ? view.token : null;
			stop();
			if (token) void api.act('release', token).catch(() => undefined);
		},
		beacon() {
			return view.phase === 'editing' && view.token ? { target, token: view.token } : null;
		},
		stop,
		view: () => view
	};
}
