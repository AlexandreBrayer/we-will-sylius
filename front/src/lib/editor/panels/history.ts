// History of a CMS page or template (D11): revisions as the builder reads them from the admin API
// (GET /cms-pages/{slug}/revisions, /cms-templates/{id}/revisions, /cms-revisions/{id}), their labels
// in French (local time of the browser), and the values outside the charte that a Charte account could
// not save again. Pure module (no GrapesJS, no Svelte), tested by test/history.test.ts. Relative `.ts`
// imports: run by node in the tests. Sylius stays the judge (CharteGuard): this module only anticipates it.
import { nodePaths, readBlocks, type StoredBlock, type TreeBlock } from '../../blocks/containers.ts';
import { readStyle, type StyleProperty } from '../../blocks/style.ts';
import { freeProperties, STYLE_LABELS } from './blockStyle.ts';

export type RevisionKind = 'page' | 'template';

/** Content whose history is shown: a page by its slug, a template by its id. */
export type RevisionTarget = { target: 'page'; slug: string } | { target: 'template'; id: number };

/** A line of the list (`cms_revision:list`). */
export interface RevisionSummary {
	id: number;
	target: RevisionKind;
	/** ISO 8601 date of the save. */
	createdAt: string;
	/** « Prénom Nom » of the author; null for the version of the fixtures. */
	author: string | null;
	status: 'draft' | 'published';
	/** Title of the page or name of the template at that time. */
	title: string;
	blockCount: number;
}

/** A revision and its blocks (`cms_revision:item`). */
export interface Revision extends RevisionSummary {
	/** Blocks as stored, layout containers with their content (#lib/blocks/containers.ts). */
	blocks: StoredBlock[];
}

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);

/** Author name of the API (`author.name`), null when absent. */
function authorName(value: unknown): string | null {
	if (!isRecord(value)) return null;
	return typeof value.name === 'string' && value.name.trim() !== '' ? value.name.trim() : null;
}

/** One line of the list; null when the answer is not one. */
export function parseRevisionSummary(raw: unknown): RevisionSummary | null {
	if (!isRecord(raw)) return null;
	const { id, target, createdAt, status, title, blockCount } = raw;
	if (typeof id !== 'number' || !Number.isInteger(id) || id < 1) return null;
	if (target !== 'page' && target !== 'template') return null;
	if (typeof createdAt !== 'string' || Number.isNaN(Date.parse(createdAt))) return null;
	return {
		id,
		target,
		createdAt,
		author: authorName(raw.author),
		status: status === 'published' ? 'published' : 'draft',
		title: typeof title === 'string' ? title : '',
		blockCount: typeof blockCount === 'number' && Number.isInteger(blockCount) && blockCount >= 0 ? blockCount : 0
	};
}

/** The list (JSON array; JSON-LD `hydra:member` / `member` accepted too); unreadable lines dropped. */
export function parseRevisionList(raw: unknown): RevisionSummary[] {
	const items = Array.isArray(raw) ? raw : isRecord(raw) ? (raw['hydra:member'] ?? raw.member) : null;
	if (!Array.isArray(items)) return [];
	return items.flatMap((item: unknown) => {
		const summary = parseRevisionSummary(item);
		return summary ? [summary] : [];
	});
}

/** Detail of a revision with its blocks (`content.blocks`); null when the answer is not one. */
export function parseRevision(raw: unknown): Revision | null {
	const summary = parseRevisionSummary(raw);
	if (!summary || !isRecord(raw)) return null;
	const content = isRecord(raw.content) ? raw.content : {};
	return { ...summary, blocks: readBlocks(content.blocks) };
}

// ── Labels (French, local time of the browser) ──

const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

const sameDay = (a: Date, b: Date): boolean => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

/** « 14 h 05 », « 9 h 00 ». */
export function timeLabel(date: Date): string {
	return `${date.getHours()} h ${String(date.getMinutes()).padStart(2, '0')}`;
}

/** « 8 novembre », « 1er mai », with the year when it is not the one of `now` (« 30 décembre 2025 »). */
export function dayLabel(date: Date, now: Date): string {
	const day = date.getDate() === 1 ? '1er' : String(date.getDate());
	const label = `${day} ${MONTHS[date.getMonth()]}`;
	return date.getFullYear() === now.getFullYear() ? label : `${label} ${date.getFullYear()}`;
}

/** Date of a line: « Aujourd'hui 14 h 05 », « Hier 9 h 30 », « 6 novembre 9 h 30 », « 30 décembre 2025 18 h 00 ». */
export function revisionDateLabel(iso: string, now: Date = new Date()): string {
	const date = new Date(iso);
	if (Number.isNaN(date.getTime())) return '';
	const yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
	if (sameDay(date, now)) return `Aujourd'hui ${timeLabel(date)}`;
	if (sameDay(date, yesterday)) return `Hier ${timeLabel(date)}`;
	return `${dayLabel(date, now)} ${timeLabel(date)}`;
}

/** Banner of the version shown: « Version du 8 novembre, 14 h 05 ». */
export function versionLabel(iso: string, now: Date = new Date()): string {
	const date = new Date(iso);
	if (Number.isNaN(date.getTime())) return 'Version';
	return `Version du ${dayLabel(date, now)}, ${timeLabel(date)}`;
}

/** « publiée » / « brouillon » for a page, « publié » / « brouillon » for a template. */
export function statusLabel(status: RevisionSummary['status'], kind: RevisionKind): string {
	if (status === 'draft') return 'brouillon';
	return kind === 'page' ? 'publiée' : 'publié';
}

/** Author of a line; the versions of the fixtures have none. */
export const authorLabel = (author: string | null): string => author ?? 'Version de démonstration';

/** A line of the list: « Aujourd'hui 14 h 05, Sophie Martin, publiée ». */
export function revisionLine(revision: RevisionSummary, now: Date = new Date()): string {
	return `${revisionDateLabel(revision.createdAt, now)}, ${authorLabel(revision.author)}, ${statusLabel(revision.status, revision.target)}`;
}

/** « aucun bloc », « 1 bloc », « 8 blocs ». */
export function blockCountLabel(count: number): string {
	if (count <= 0) return 'aucun bloc';
	return count === 1 ? '1 bloc' : `${count} blocs`;
}

/** « Aucune version enregistrée », « 1 version », « 12 versions ». */
export function versionCountLabel(count: number): string {
	if (count <= 0) return 'Aucune version enregistrée';
	return count === 1 ? '1 version' : `${count} versions`;
}

// ── Values outside the charte (CharteGuard) ──

/**
 * A free value of a block (Content\Domain\StyleDeviation): position of its root node from 1, its French path
 * (« Bloc n°2 », « Section 1 › colonnes 2 › colonne 1 › bloc 1 », containers.ts nodePaths), property, value.
 */
export interface Deviation {
	block: number;
	where: string;
	property: StyleProperty;
	value: string;
}

/** Free values of the blocks (#rrggbb, px), nested ones included, in document order then in the order of BlockStyle::PROPERTIES. */
export function deviations(blocks: readonly TreeBlock[]): Deviation[] {
	return nodePaths(blocks).flatMap(({ node, root, where }) => {
		const style = readStyle(node.style);
		return freeProperties(style).map((property) => ({ block: root, where, property, value: style[property] ?? '' }));
	});
}

const deviationKey = (deviation: Deviation): string => `${deviation.property}=${deviation.value.toLowerCase()}`;

/**
 * Free values of `blocks` beyond those of `stored` (CharteGuard::newDeviations): compared as a multiset of
 * (property, value) pairs, not by position. Those are what Sylius refuses to a Charte account: restoring
 * a version whose free value was removed since gives a 422 at the save.
 */
export function newDeviations(blocks: readonly TreeBlock[], stored: readonly TreeBlock[]): Deviation[] {
	const available = new Map<string, number>();
	for (const deviation of deviations(stored)) available.set(deviationKey(deviation), (available.get(deviationKey(deviation)) ?? 0) + 1);
	return deviations(blocks).filter((deviation) => {
		const left = available.get(deviationKey(deviation)) ?? 0;
		if (left > 0) available.set(deviationKey(deviation), left - 1);
		return left === 0;
	});
}

/** « bloc n°2 : Fond #ff7700 ; section 1 › bloc 3 : Taille du titre 60px ». */
export function describeDeviations(list: readonly Deviation[]): string {
	return list.map((deviation) => `${deviation.where.charAt(0).toLowerCase()}${deviation.where.slice(1)} : ${STYLE_LABELS[deviation.property]} ${deviation.value}`).join(' ; ');
}

/**
 * Warning shown before « Restaurer cette version » to a Charte account when the version holds free
 * values that the stored content no longer has; null otherwise (other levels, nothing new).
 */
export function charteWarning(level: string, list: readonly Deviation[]): string | null {
	if (level !== 'charte' || list.length === 0) return null;
	const several = list.length > 1;
	return `Cette version contient ${several ? 'des valeurs hors charte retirées' : 'une valeur hors charte retirée'} depuis (${describeDeviations(list)}). Votre compte (niveau Charte) ne pourra pas l'enregistrer telle quelle : après la restauration, remplacez ${several ? 'ces valeurs' : 'cette valeur'} par ${several ? 'des valeurs' : 'une valeur'} de la charte, ou demandez à un compte Libre de la restaurer.`;
}
