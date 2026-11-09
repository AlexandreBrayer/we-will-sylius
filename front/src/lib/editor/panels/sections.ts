// Reusable sections of the builder (D12): sections as the builder reads them from the admin API
// (GET /cms-sections), the rule of their name (same as Content\Domain\CmsSectionName), and what the
// palette and « Enregistrer comme section » say in French. Pure module (no GrapesJS, no Svelte), tested
// by test/sections.test.ts; relative `.ts` imports: run by node in the tests. Sylius stays the judge
// (Application\CreateCmsSection, CharteGuard): this module only anticipates it.
import { countNodes, flattenNodes, readBlocks, type StoredBlock, type TreeBlock } from '../../blocks/containers.ts';
import { readStyle } from '../../blocks/style.ts';
import { freeProperties, STYLE_LABELS } from './blockStyle.ts';
import { blockCountLabel, describeDeviations, deviations, type Deviation } from './history.ts';

/** Longest name (CmsSectionName::MAX). */
export const SECTION_NAME_MAX = 120;

/** Most blocks in a section (PageBlocks::MAX_BLOCKS), layout containers counted. */
export const SECTION_BLOCKS_MAX = 30;

export const SECTION_MESSAGES = {
	nameMissing: 'Donnez un nom à la section.',
	nameTooLong: `Le nom de la section fait au plus ${SECTION_NAME_MAX} caractères.`,
	noBlock: 'Sélectionnez un bloc dans la page.',
	tooManyBlocks: `Une section contient au plus ${SECTION_BLOCKS_MAX} blocs.`
} as const;

/** A section of « Mes sections » (`cms_section:read`). */
export interface Section {
	id: number;
	name: string;
	/** Blocks as stored, layout containers with their content (#lib/blocks/containers.ts). */
	blocks: StoredBlock[];
	/** « Prénom Nom » of the administrator who saved it; null when saved without one. */
	author: string | null;
	createdAt: string;
	updatedAt: string;
}

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);

/** One section; null when the answer is not one (no id, no name, no block). */
export function parseSection(raw: unknown): Section | null {
	if (!isRecord(raw)) return null;
	const { id, name, author, createdAt, updatedAt } = raw;
	if (typeof id !== 'number' || !Number.isInteger(id) || id < 1) return null;
	if (typeof name !== 'string' || name.trim() === '') return null;
	const blocks = readBlocks(raw.blocks);
	if (blocks.length === 0) return null;
	return {
		id,
		name,
		blocks,
		author: typeof author === 'string' && author.trim() !== '' ? author.trim() : null,
		createdAt: typeof createdAt === 'string' ? createdAt : '',
		updatedAt: typeof updatedAt === 'string' ? updatedAt : ''
	};
}

/** The list (JSON array; JSON-LD `hydra:member` / `member` accepted too); unreadable sections dropped. */
export function parseSectionList(raw: unknown): Section[] {
	const items = Array.isArray(raw) ? raw : isRecord(raw) ? (raw['hydra:member'] ?? raw.member) : null;
	if (!Array.isArray(items)) return [];
	return items.flatMap((item: unknown) => {
		const section = parseSection(item);
		return section ? [section] : [];
	});
}

// ── Name (CmsSectionName) ──

/** One line: line breaks, spaces, control and format characters give one space each run; trimmed. */
export function cleanSectionName(name: string): string {
	return name.replace(/[\p{Cc}\p{Cf}\p{Z}]+/gu, ' ').trim();
}

/** Why the name is refused, null when Sylius accepts it. */
export function sectionNameIssue(name: string): string | null {
	const clean = cleanSectionName(name);
	if (clean === '') return SECTION_MESSAGES.nameMissing;
	return [...clean].length > SECTION_NAME_MAX ? SECTION_MESSAGES.nameTooLong : null;
}

// ── Values outside the charte ──

/** « Fond #ff7700, Taille du titre 60px » for one block; with its path (« bloc n°2 : … ; section 1 › bloc 1 : … ») for several. */
function describe(list: readonly Deviation[], blockCount: number): string {
	if (blockCount <= 1) return list.map((deviation) => `${STYLE_LABELS[deviation.property]} ${deviation.value}`).join(', ');
	return describeDeviations(list);
}

/** The section holds at least one free value (#rrggbb, px), nested blocks included: shown on its line in the palette. */
export const holdsFreeValues = (blocks: readonly TreeBlock[]): boolean => flattenNodes(blocks).some((block) => freeProperties(readStyle(block.style)).length > 0);

/**
 * Why the selected blocks cannot be saved as a section (what Sylius would refuse), null when they can:
 * no block, too many, a block bound to the template (`bound`: their labels), or, for a Charte account, a
 * free value (a section is new content: every free value in it is new, CharteGuard).
 */
export function saveIssue(blocks: readonly TreeBlock[], bound: readonly string[], level: string): string | null {
	if (blocks.length === 0 && bound.length === 0) return SECTION_MESSAGES.noBlock;
	if (bound.length > 0) {
		const several = bound.length > 1;
		return `${bound.join(', ')} : ${several ? 'ces blocs sont liés' : 'ce bloc est lié'} à la fiche du template et ne ${several ? 's’enregistrent' : 's’enregistre'} pas comme section. Seuls les blocs Yack libres le peuvent.`;
	}
	const count = countNodes(blocks);
	if (count > SECTION_BLOCKS_MAX) return SECTION_MESSAGES.tooManyBlocks;
	const free = deviations(blocks);
	if (level !== 'charte' || free.length === 0) return null;
	const severalValues = free.length > 1;
	return `${count > 1 ? 'Ces blocs portent' : 'Ce bloc porte'} ${severalValues ? 'des valeurs hors charte' : 'une valeur hors charte'} (${describe(free, count)}) : votre compte (niveau Charte) ne peut pas ${count > 1 ? 'les ' : 'l’'}enregistrer comme section. Remplacez d’abord ${severalValues ? 'ces valeurs par des valeurs' : 'cette valeur par une valeur'} de la charte (onglet Style).`;
}

/**
 * Warning shown before inserting a section that holds free values, to a Charte account: the copy adds
 * them to the page, and Sylius refuses to save a page with a free value it did not hold (CharteGuard).
 * null otherwise (other levels, nothing outside the charte).
 */
export function insertWarning(section: Pick<Section, 'name' | 'blocks'>, level: string): string | null {
	const free = deviations(section.blocks);
	if (level !== 'charte' || free.length === 0) return null;
	const several = free.length > 1;
	return `La section « ${section.name} » contient ${several ? 'des valeurs hors charte' : 'une valeur hors charte'} (${describe(free, countNodes(section.blocks))}). Votre compte (niveau Charte) ne pourra pas enregistrer la page tant qu’${several ? 'elles y restent' : 'elle y reste'} : après l’insertion, remplacez-${several ? 'les' : 'la'} par ${several ? 'des valeurs' : 'une valeur'} de la charte (onglet Style), ou annulez l’insertion (Ctrl+Z).`;
}

// ── Labels ──

/** Second line of a section in the palette: « 2 blocs · Sophie Martin » (layout containers counted, like Sylius). */
export function sectionDetail(section: Pick<Section, 'blocks' | 'author'>): string {
	const count = blockCountLabel(countNodes(section.blocks));
	return section.author ? `${count} · ${section.author}` : count;
}

/** Confirmation of « Enregistrer comme section ». */
export const savedMessage = (name: string): string => `Section « ${name} » enregistrée : elle est dans « Mes sections » (onglet Blocs).`;

/** After an insertion: the warning of a Charte account, or what an insertion is. */
export function insertedMessage(section: Pick<Section, 'name' | 'blocks'>, level: string): { kind: 'ok' | 'error'; text: string } {
	const warning = insertWarning(section, level);
	if (warning) return { kind: 'error', text: `Section insérée. ${warning}` };
	return { kind: 'ok', text: `Section « ${section.name} » insérée : ses blocs sont des copies, les modifier ne change pas la section (Ctrl+Z annule l’insertion).` };
}

/** Question asked before deleting a section. */
export const deleteQuestion = (name: string): string => `Supprimer la section « ${name} » ? Les pages où elle a été insérée ne changent pas.`;
