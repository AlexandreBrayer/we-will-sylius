// Thumbnails of the palette (E6): a 64 x 40 diagram of the layout of each block, in two greys taken from
// the colour of the text (`currentColor`, light and dark opacities), no colour of their own. Decorative
// (aria-hidden): the button of the block carries its label. Basic blocks to come (B8-6: titre, texte,
// bouton, espace, image) already have theirs; a block without one shows the icon of the registry.

/** Light grey: surfaces (background, image, card). */
const light = (x: number, y: number, width: number, height: number, radius = 1.5) =>
	`<rect x="${x}" y="${y}" width="${width}" height="${height}" rx="${radius}" fill-opacity=".18"/>`;

/** Dark grey: what is read (titles, text lines, buttons, pictos). */
const dark = (x: number, y: number, width: number, height: number, radius = 1) =>
	`<rect x="${x}" y="${y}" width="${width}" height="${height}" rx="${radius}" fill-opacity=".5"/>`;

const svg = (body: string) =>
	`<svg viewBox="0 0 64 40" width="64" height="40" fill="currentColor" aria-hidden="true" focusable="false">${body}</svg>`;

/** Text lines of a column: `widths` from the top, every 5 px. */
const lines = (x: number, y: number, widths: number[]) => widths.map((width, i) => dark(x, y + i * 5, width, 2)).join('');

/** One decimal at most in the drawing. */
const n = (value: number) => Math.round(value * 10) / 10;

/** Picture: light frame, dark hill and sun. */
const picture = (x: number, y: number, width: number, height: number) =>
	light(x, y, width, height) +
	`<path d="M${x + 3} ${y + height - 3}l${n(width * 0.3)} ${n(-height * 0.4)}l${n(width * 0.2)} ${n(height * 0.22)}l${n(width * 0.15)} ${n(-height * 0.12)}l${n(width * 0.2)} ${n(height * 0.3)}z" fill-opacity=".5"/>` +
	`<circle cx="${n(x + width * 0.75)}" cy="${n(y + height * 0.3)}" r="${n(Math.min(width, height) * 0.1)}" fill-opacity=".5"/>`;

/** Product card: light visual, dark name and price. */
const card = (x: number) => light(x, 5, 12, 18) + dark(x, 26, 12, 2) + dark(x, 31, 8, 2);

/** Document line: dark file, light label. */
const fileLine = (y: number) => dark(8, y, 6, 8) + light(18, y + 2, 38, 4);

export const THUMBS: Readonly<Record<string, string>> = {
	// Mise en page: basic blocks (B8-6), then the containers.
	titre: svg(dark(8, 12, 44, 7) + light(8, 24, 30, 4)),
	texte: svg(lines(8, 7, [48, 48, 30]) + [48, 40].map((width, i) => light(8, 26 + i * 5, width, 2, 1)).join('')),
	bouton: svg(dark(16, 13, 32, 14, 7) + `<rect x="24" y="19" width="16" height="2" rx="1" fill-opacity=".4"/>`),
	espace: svg(light(4, 3, 56, 8) + light(4, 29, 56, 8) + dark(31, 14, 2, 12) + dark(26, 13, 12, 1.5) + dark(26, 25.5, 12, 1.5)),
	'texte-image': svg(picture(4, 5, 26, 30) + dark(35, 8, 22, 4) + lines(35, 16, [25, 25, 20, 14])),
	image: svg(picture(8, 4, 48, 32)),
	section: svg(light(2, 2, 60, 36, 3) + dark(8, 9, 30, 4) + lines(8, 17, [48, 48, 32])),
	colonnes: svg(light(4, 4, 26, 32) + light(34, 4, 26, 32) + lines(8, 9, [18, 14]) + lines(38, 9, [18, 14])),
	// Blocs Yack.
	hero: svg(light(2, 4, 60, 32, 2) + dark(8, 10, 26, 4) + lines(8, 17, [22, 18]) + dark(8, 27, 12, 5, 2.5) + dark(42, 9, 15, 22, 1.5)),
	produits: svg(card(4) + card(19) + card(34) + card(49)),
	categories: svg(light(4, 4, 27, 15) + light(33, 4, 27, 15) + light(4, 21, 27, 15) + light(33, 21, 27, 15) + dark(8, 10, 14, 3) + dark(37, 10, 14, 3) + dark(8, 27, 14, 3) + dark(37, 27, 14, 3)),
	cta: svg(light(2, 8, 60, 24, 4) + dark(8, 13, 24, 4) + dark(8, 21, 18, 2) + dark(42, 15, 14, 9, 4.5)),
	faq: svg([5, 16, 27].map((y) => light(4, y, 56, 8) + dark(8, y + 3, 28, 2) + dark(53, y + 3, 3, 2)).join('')),
	reassurance: svg([10, 25, 40, 55].map((cx) => `<circle cx="${cx}" cy="13" r="4.5" fill-opacity=".5"/>` + light(cx - 6, 22, 12, 3) + light(cx - 4, 28, 8, 2)).join('')),
	'chiffres-cles': svg([5, 25, 45].map((x) => dark(x, 10, 14, 9) + light(x, 23, 14, 3)).join('')),
	'acces-rapides': svg([[4, 4], [33, 4], [4, 21], [33, 21]].map(([x, y]) => light(x, y, 27, 15, 3) + dark(x + 3, y + 5, 5, 5) + dark(x + 11, y + 6, 12, 3)).join('')),
	tuiles: svg(light(4, 4, 27, 32) + light(34, 4, 12, 15) + light(48, 4, 12, 15) + light(34, 21, 12, 15) + light(48, 21, 12, 15) + dark(7, 30, 14, 3) + dark(36, 14, 7, 2) + dark(50, 14, 7, 2) + dark(36, 31, 7, 2) + dark(50, 31, 7, 2)),
	accompagnement: svg([4, 24, 44].map((x) => light(x, 4, 16, 32) + dark(x + 3, 20, 10, 3) + dark(x + 3, 26, 8, 2)).join('')),
	documents: svg(fileLine(5) + fileLine(16) + fileLine(27)),
	// Blocs produit.
	'produit-galerie': svg(picture(4, 3, 56, 25) + [4, 18, 32, 46].map((x) => dark(x, 31, 12, 6)).join('')),
	'produit-infos': svg(dark(6, 6, 36, 5) + light(6, 15, 20, 3) + lines(6, 22, [52, 52, 34])),
	'produit-achat': svg(dark(6, 6, 22, 7) + light(6, 17, 34, 3) + dark(6, 25, 40, 10, 5)),
	'produit-caracteristiques': svg([4, 18, 32].map((y) => light(4, y, 56, 6)).join('') + [4, 11, 18, 25, 32].map((y) => dark(7, y + 2, 14, 2) + dark(30, y + 2, 10, 2)).join('')),
	'produit-description': svg(dark(6, 6, 28, 5) + lines(6, 16, [52, 52, 30]) + light(6, 31, 52, 2, 1)),
	'produit-documents': svg(fileLine(5) + fileLine(16) + fileLine(27)),
	'produit-sav': svg(light(4, 5, 56, 30, 3) + dark(10, 11, 30, 4) + light(10, 21, 30, 7, 3.5) + dark(43, 21, 11, 7, 3.5)),
	'produits-associes': svg(card(4) + card(19) + card(34) + card(49)),
	// Blocs catégorie.
	'categorie-entete': svg(light(2, 4, 60, 32, 2) + dark(8, 10, 32, 7) + lines(8, 23, [48, 36])),
	'categorie-produits': svg(lines(4, 6, [10, 10, 10, 10]) + [18, 33, 48].map((x) => light(x, 4, 12, 15) + light(x, 21, 12, 15)).join('')),
	'categorie-sous-categories': svg(dark(4, 16, 14, 8, 4) + light(20, 16, 12, 8, 4) + light(34, 16, 12, 8, 4) + light(48, 16, 12, 8, 4))
};

/** Thumbnail of a block of the palette, if it has one. */
export function blockThumb(type: string): string | undefined {
	return Object.hasOwn(THUMBS, type) ? THUMBS[type] : undefined;
}
