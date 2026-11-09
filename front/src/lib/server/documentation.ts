// Documentation assistant on the product page: POST /shop/yack/documentation-questions and
// GET /shop/yack/documentation-coverages/{code}, for logged-in customers (PRO and ECOM; the API answers
// 401 to a visitor, so no call is made for one). Mapping: #lib/documentation/types.ts.
import { toAnswer, toCoverage, type DocumentationAnswer, type DocumentationCoverage } from '#lib/documentation/types.js';
import { sylius, type ShopEvent } from './sylius.js';

/** Notices of the product and its sample question; null for a visitor (the panel invites to log in). */
export async function documentationCoverage(event: ShopEvent, productCode: string): Promise<DocumentationCoverage | null> {
	if (!event.locals.viewer.authenticated) return null;
	const raw = await sylius(event).get<unknown>(`/yack/documentation-coverages/${encodeURIComponent(productCode)}`);
	return toCoverage(raw, productCode);
}

/** Question asked from the product page `productCode` (empty: whole documentation). */
export async function askDocumentation(event: ShopEvent, question: string, productCode: string): Promise<DocumentationAnswer> {
	const raw = await sylius(event).post<unknown>('/yack/documentation-questions', {
		question: question.trim(),
		productCode: productCode.trim() === '' ? null : productCode.trim()
	});
	return toAnswer(raw);
}
