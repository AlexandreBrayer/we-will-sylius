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

/** "Label | link" lines (documents block): same format as the FAQ, link resolved by resolveLink. */
export function parseLinks(value: string | undefined): { label: string; url: string }[] {
	return parseFaq(value)
		.map(({ question, answer }) => ({ label: question, url: resolveLink(answer) }))
		.filter((item) => item.url !== '');
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
