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

/** "page:<slug>" = CMS page, "/fr_FR/..." = Sylius shop, anything else as is. */
export function resolveLink(value: string | undefined, syliusUrl: string): string {
	const link = (value ?? '').trim();
	if (link.startsWith('page:')) return `/${link.slice(5)}`;
	if (link.startsWith('/')) return `${syliusUrl}${link}`;
	return link;
}

/** Visuals live in the Sylius public dir (public/yack/cms). */
export function assetUrl(path: string, syliusUrl: string): string {
	return `${syliusUrl}/${path.replace(/^\//, '')}`;
}
