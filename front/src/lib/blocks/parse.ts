// Same rules as App\Yack\Content\Domain\PageBlocks (codes, FAQ) and CmsExtension::link().

export function parseCodes(value: string | undefined): string[] {
	return [
		...new Set(
			(value ?? '')
				.split(/[\s,;]+/)
				.map((code) => code.trim())
				.filter(Boolean)
		)
	];
}

export function parseFaq(value: string | undefined): { question: string; answer: string }[] {
	return (value ?? '')
		.split(/\r?\n/)
		.map((line) => {
			const [question, ...answer] = line.split('|');
			return { question: question.trim(), answer: answer.join('|').trim() };
		})
		.filter((item) => item.question !== '');
}

/**
 * "page:<slug>" = CMS page, a front path ("/sav") or an http(s) / mailto / tel URL as is.
 * Any other scheme ("javascript:", "data:"…) gives an empty link: block props are free text.
 */
export function resolveLink(value: string | undefined): string {
	const link = (value ?? '').trim();
	if (link.startsWith('page:')) {
		const slug = link.slice(5);
		return /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug) ? `/${slug}` : '';
	}
	// Same-site path; "//host" and "/\host" would leave the site (protocol-relative URL).
	if (link.startsWith('/')) return /^\/(?![\/\\])/.test(link) ? link : '';
	return /^(https?:\/\/|mailto:|tel:)/i.test(link) ? link : '';
}

export const MAX_LINK_LENGTH = 2000;

const LINK_SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
/** Host of an http(s) link: optional user info, a name or an IPv6 address in brackets, optional port. */
const LINK_WEB = /^https?:\/\/(?:[^/\\?#@]*@)?(?:\[[0-9a-f:.]+\]|[^/\\?#@:[\]<>^|%"]+)(?::[0-9]{1,5})?(?:[/?#].*)?$/iu;
const LINK_TEL = /^tel:(?=[^0-9]*[0-9])[0-9+ .()-]+$/i;
/** Whitespace, control and format characters (zero width, bidirectional marks). */
const LINK_BLANK = /[\s\p{Z}\p{Cc}\p{Cf}]/u;
/** Numeric or named character reference. */
const LINK_REFERENCE = /&(?:#[0-9]+|#x[0-9a-f]+|[a-z][a-z0-9]*);/i;

/**
 * Whether `link` is a target Sylius accepts for a link (same rule as App\Yack\Content\Domain\BlockLink,
 * same cases in test/link.test.ts and tests/Yack/Content/Domain/BlockLinkTest.php): empty, `page:<slug>`,
 * a path of the site starting with a single `/`, `http(s)://` with a host, `mailto:` with an address, `tel:`
 * with digits, `+`, ASCII spaces, dots, hyphens, parentheses. Never a blank, control or format character
 * (zero width space…) outside the spaces of a `tel:`, never a character reference (`&#106;`, `&colon;`).
 * Not trimmed: the value as stored.
 */
export function isAllowedLink(link: string): boolean {
	if (link === '') return true;
	// Lone surrogate: what PHP sees as invalid UTF-8. Length in code points, as mb_strlen.
	if (/[\ud800-\udfff]/u.test(link) || [...link].length > MAX_LINK_LENGTH || LINK_REFERENCE.test(link)) return false;
	if (/^tel:/i.test(link)) return LINK_TEL.test(link);
	if (LINK_BLANK.test(link)) return false;
	if (link.startsWith('page:')) return LINK_SLUG.test(link.slice(5));
	if (link.startsWith('/')) return link[1] !== '/' && link[1] !== '\\';
	if (/^https?:/i.test(link)) return LINK_WEB.test(link);
	return /^mailto:./i.test(link);
}

/** Link of a « Libellé | lien » line of the documents block: resolved (resolveLink) when Sylius allows it, '' otherwise. */
export function documentLink(value: string | undefined): string {
	const link = (value ?? '').trim();
	return isAllowedLink(link) ? resolveLink(link) : '';
}

/** "Label | link" lines (documents block): same format as the FAQ, link kept when Sylius allows it (documentLink). */
export function parseLinks(value: string | undefined): { label: string; url: string }[] {
	return parseFaq(value)
		.map(({ question, answer }) => ({ label: question, url: documentLink(answer) }))
		.filter((item) => item.url !== '');
}

/**
 * Paragraphs of a multiline text (« Texte » block): separated by a blank line (spaces allowed on it), each one
 * keeps its single line breaks (shown by `white-space: pre-line`); blank lines around a paragraph and empty
 * paragraphs are dropped. Plain text: the component escapes it.
 */
export function parseParagraphs(value: string | undefined): string[] {
	return (value ?? '')
		.replace(/\r\n?/g, '\n')
		.split(/\n[^\S\n]*\n/)
		.map((paragraph) => paragraph.replace(/^(?:[^\S\n]*\n)+/, '').replace(/(?:\n[^\S\n]*)+$/, ''))
		.filter((paragraph) => paragraph.trim() !== '');
}

/** Profiles of the "audience" prop (App\Yack\Content\Domain\BlockType::AUDIENCES). */
export function isVisibleFor(audience: string | undefined, profile: 'visitor' | 'pro' | 'ecom'): boolean {
	switch (audience ?? 'tous') {
		case 'visiteur':
			return profile === 'visitor';
		case 'pro':
			return profile === 'pro';
		case 'ecom':
			return profile === 'ecom';
		case 'connectes':
			return profile !== 'visitor';
		default:
			return true;
	}
}

/** Visuals live in the Sylius public dir (public/yack/cms), served by nginx on the same origin. */
export function assetUrl(path: string): string {
	return `/${path.replace(/^\//, '')}`;
}
