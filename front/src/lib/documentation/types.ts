// Documentation assistant (product page): shapes of POST /shop/yack/documentation-questions and
// GET /shop/yack/documentation-coverages/{code} (docs/API_FRONT.md), mapped defensively. Pure module
// (no SvelteKit import): tested by `npm test`. The rules (rights, lengths, sources) are the API's.

/** Same bounds as Question::MIN_LENGTH / MAX_LENGTH (Sylius). */
export const QUESTION_MIN = 3;
export const QUESTION_MAX = 300;

/** Sentence of the API when the notices do not answer (Answer::NOT_FOUND). */
export const NOT_FOUND = 'Je ne trouve pas la réponse dans la documentation.';

export const UNAVAILABLE = "L'assistant documentation est indisponible pour le moment : réessayez dans quelques minutes.";

export interface DocumentationSource {
	source: string;
	page: number;
}

export interface DocumentationAnswer {
	/** Plain text, shown as text (never as HTML). */
	answer: string;
	sources: DocumentationSource[];
	/** Written by the demonstration adapter: SmartTag `simulated`. */
	simulated: boolean;
}

export interface DocumentationCoverage {
	productCode: string;
	/** Titles of the notices of the product (empty: no notice). */
	notices: string[];
	exampleQuestion: string | null;
}

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null;

/** API answer → front type; a source without name or valid page is dropped, no source left = "je ne trouve pas". */
export function toAnswer(raw: unknown): DocumentationAnswer {
	const body = isRecord(raw) ? raw : {};
	const sources = (Array.isArray(body.sources) ? body.sources : []).flatMap((item): DocumentationSource[] => {
		if (!isRecord(item) || typeof item.source !== 'string' || item.source.trim() === '') return [];
		const page = Number(item.page);
		return Number.isInteger(page) && page >= 1 ? [{ source: item.source.trim(), page }] : [];
	});
	const answer = typeof body.answer === 'string' ? body.answer.trim() : '';

	return {
		answer: sources.length > 0 && answer !== '' ? answer : NOT_FOUND,
		sources: answer !== '' ? sources : [],
		simulated: body.simulated === true
	};
}

export function toCoverage(raw: unknown, productCode: string): DocumentationCoverage {
	const body = isRecord(raw) ? raw : {};
	const notices = (Array.isArray(body.notices) ? body.notices : []).filter((title): title is string => typeof title === 'string' && title !== '');
	const example = typeof body.exampleQuestion === 'string' && body.exampleQuestion.trim() !== '' ? body.exampleQuestion.trim() : null;

	return { productCode: typeof body.productCode === 'string' ? body.productCode : productCode, notices, exampleQuestion: notices.length > 0 ? example : null };
}

/**
 * Whether the product page shows the panel: always for a visitor (the coverage is read by logged-in customers only,
 * the panel invites to log in); for a logged-in customer, only when the product has at least one notice (no panel
 * that could only answer "je ne trouve pas").
 */
export function showsPanel(authenticated: boolean, coverage: DocumentationCoverage | null): boolean {
	return !authenticated || (coverage !== null && coverage.notices.length > 0);
}

/** "Source : <notice>, page <N>" (text of .yack-smart-panel__source). */
export function sourceLabel(source: DocumentationSource): string {
	return `Source : ${source.source}, page ${source.page}`;
}
