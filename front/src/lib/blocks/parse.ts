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

/** "page:<slug>" = CMS page, anything else (front path "/sav", "https://…") as is. */
export function resolveLink(value: string | undefined): string {
	const link = (value ?? '').trim();
	return link.startsWith('page:') ? `/${link.slice(5)}` : link;
}

/** Visuals live in the Sylius public dir (public/yack/cms), served by nginx on the same origin. */
export function assetUrl(path: string): string {
	return `/${path.replace(/^\//, '')}`;
}
