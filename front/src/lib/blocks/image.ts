// Settings of an image prop of a block (BlockType::imageSettingProps()): `<image>Alt` (alternative
// text, '' = decorative image), `<image>Fit` (framing) and `<image>Focus` (focal point of a cropped
// image). Rendered as the `alt` of the image and `yack-cms-img--<value>` classes
// (assets/shop/styles/_cms-images.scss): an image without settings keeps the markup of its block.
// Pure module: tested by `npm test` (test/image.test.ts).

/** Framings (BlockType::IMAGE_FITS without '', the block's own framing). */
export const IMAGE_FITS = ['couvrir', 'contenir'] as const;

/** Focal points (BlockType::IMAGE_FOCUSES without '', the block's own: centre). */
export const IMAGE_FOCUSES = ['haut', 'centre', 'bas'] as const;

export type ImageFit = (typeof IMAGE_FITS)[number];
export type ImageFocus = (typeof IMAGE_FOCUSES)[number];

/** Longest alternative text of an image of a block (CmsMediaFile::ALT_MAX, BlockType::isImageAlt()). */
export const IMAGE_ALT_MAX = 255;

/**
 * Alternative text as Sylius accepts it (BlockType::isImageAlt()): one line, spaces collapsed, without
 * control characters nor `<` or `>`, IMAGE_ALT_MAX characters at most.
 */
export function cleanImageAlt(text: string): string {
	const line = text.replace(/[<>]/g, '').replace(/[\s\u0000-\u001f\u007f-\u009f]+/g, ' ').trim();
	return Array.from(line).slice(0, IMAGE_ALT_MAX).join('').trim();
}

/** Names of the setting props of the image prop `prop` (`image` gives imageAlt, imageFit, imageFocus). */
export function imageSettingNames(prop: string): { alt: string; fit: string; focus: string } {
	return { alt: `${prop}Alt`, fit: `${prop}Fit`, focus: `${prop}Focus` };
}

const isFit = (value: string | undefined): value is ImageFit => IMAGE_FITS.includes(value as ImageFit);
const isFocus = (value: string | undefined): value is ImageFocus => IMAGE_FOCUSES.includes(value as ImageFocus);

/**
 * `alt` and `class` of the image of `prop`. `base`: class the block already puts on the image. A value
 * outside the choices (stored content that Sylius would refuse today) is ignored. No setting: `class`
 * is `base` (undefined without it), so the rendered markup is the one of the block without settings.
 */
export function imageView(props: Record<string, string | undefined>, prop: string, base?: string): { alt: string; class: string | undefined } {
	const names = imageSettingNames(prop);
	const fit = props[names.fit];
	const focus = props[names.focus];
	const classes = [base, isFit(fit) ? `yack-cms-img--${fit}` : null, isFocus(focus) ? `yack-cms-img--${focus}` : null].filter(
		(name): name is string => typeof name === 'string' && name !== ''
	);
	return {
		alt: (props[names.alt] ?? '').replace(/\s+/g, ' ').trim(),
		class: classes.length > 0 ? classes.join(' ') : undefined
	};
}
