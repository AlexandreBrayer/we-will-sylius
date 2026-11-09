// « La pièce par une photo » (page /sav): answer of POST /api/v2/shop/yack/sav/nameplate-readings, rules of the
// photo (the same in the browser, in the server check and in the API) and what the panel shows.
// Pure module without import: tested by `npm test` (test/nameplate.test.ts).

/** Status of the reading, as given by the API (`unavailable` is its 503 answer). */
export type NameplateStatus = 'found' | 'product' | 'unreadable' | 'unknown';

/** Catalog product of the model reference read on the label (status `product`). */
export interface NameplateProduct {
	code: string;
	name: string;
	/** Product sheet: /produit/{slug}. */
	slug: string;
	brand: string | null;
	/** Names of the documents Yack publishes for the product (notices, documentation). */
	documents: string[];
}

/** POST yack/sav/nameplate-readings (multipart, field `photo`). */
export interface NameplateReading {
	status: NameplateStatus;
	/** Normalized serial number read on the plate, null when none was read. */
	serialNumber: string | null;
	/** Model reference printed on the plate (SRK25ZS-W), as read. */
	reference: string | null;
	/** Model name printed on the plate, as read. */
	model: string | null;
	product: NameplateProduct | null;
	/** Answer of the demo reader (YACK_AI_ADAPTER=fake). */
	simulated: boolean;
}

// ── The photo ──

/** `accept` of the input: every image (Safari iOS hands over JPEG for the camera; HEIC is converted in the browser). */
export const NAMEPLATE_ACCEPT = 'image/*';

/** Largest file sent: same value as UPLOAD_MAX_BYTES (#lib/server/upload.ts) and NameplatePhoto::MAX_BYTES (API). */
export const NAMEPLATE_MAX_BYTES = 20 * 1024 * 1024;

/** Types and extensions the API reads (type read on the content); anything else is converted or refused before. */
export const NAMEPLATE_TYPES = ['image/jpeg', 'image/png', 'image/webp'] as const;
export const NAMEPLATE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'] as const;

const HEIC_TYPES = ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'];
const HEIC_EXTENSIONS = ['heic', 'heif'];

export const PHOTO_MESSAGES = {
	missing: 'Prenez ou choisissez une photo de la plaque.',
	empty: 'Le fichier est vide.',
	tooLarge: 'Fichier trop volumineux (20 Mo maximum)',
	heic: 'Photo HEIC non lue : envoyez-la en JPEG, PNG ou WebP.',
	format: 'Format non accepté (JPEG, PNG ou WebP).',
	network: "La photo n'a pas pu être envoyée : vérifiez la connexion, puis réessayez."
} as const;

/** Longest side and JPEG quality of a photo reduced in the browser before the upload. */
export const PHOTO_MAX_SIDE = 1600;
export const PHOTO_JPEG_QUALITY = 0.85;

/** Below this weight, a JPEG / PNG / WebP of PHOTO_MAX_SIDE at most is sent as is (the demo plates keep their PNG marker). */
export const PHOTO_LIGHT_BYTES = 2 * 1024 * 1024;

type PhotoFile = { name: string; type: string; size: number };

function extensionOf(name: string): string {
	const dot = name.lastIndexOf('.');
	return dot <= 0 || dot === name.length - 1 ? '' : name.slice(dot + 1).toLowerCase();
}

/** `accepted`: read by the API as is; `heic`: iPhone photo to convert; `other`: anything else. */
export function photoKind(file: Pick<PhotoFile, 'name' | 'type'>): 'accepted' | 'heic' | 'other' {
	const type = file.type.toLowerCase();
	const extension = extensionOf(file.name);
	if (HEIC_TYPES.includes(type) || HEIC_EXTENSIONS.includes(extension)) return 'heic';
	if ((NAMEPLATE_TYPES as readonly string[]).includes(type) || (NAMEPLATE_EXTENSIONS as readonly string[]).includes(extension)) return 'accepted';
	return 'other';
}

/** Why the file cannot be sent to the API as is, null when it can (the API checks the content again). */
export function photoIssue(file: PhotoFile): string | null {
	if (file.size === 0) return PHOTO_MESSAGES.empty;
	const kind = photoKind(file);
	if (kind === 'heic') return PHOTO_MESSAGES.heic;
	if (kind === 'other') return PHOTO_MESSAGES.format;
	if (file.size > NAMEPLATE_MAX_BYTES) return PHOTO_MESSAGES.tooLarge;
	return null;
}

/** Size of the photo once reduced to `max` on its longest side (never enlarged). */
export function reducedSize(width: number, height: number, max = PHOTO_MAX_SIDE): { width: number; height: number } {
	const longest = Math.max(width, height);
	if (longest <= max) return { width, height };
	const ratio = max / longest;
	return { width: Math.max(1, Math.round(width * ratio)), height: Math.max(1, Math.round(height * ratio)) };
}

/** Whether the browser re-encodes the photo before the upload: HEIC, unknown type, large or heavy image. */
export function needsReduction(file: PhotoFile, width: number, height: number): boolean {
	return photoKind(file) !== 'accepted' || Math.max(width, height) > PHOTO_MAX_SIDE || file.size > PHOTO_LIGHT_BYTES;
}

/** Name of the reduced photo: same base name, `.jpg`. */
export function jpegName(name: string): string {
	const base = name.replace(/\.[^./\\]*$/, '').trim();
	return `${base || 'photo'}.jpg`;
}

/** « 312 Ko », « 4,2 Mo ». */
export function formatBytes(bytes: number): string {
	if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} Ko`;
	return `${(bytes / (1024 * 1024)).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} Mo`;
}

// ── What the panel shows ──

export const NAMEPLATE_MESSAGES = {
	product: 'Modèle reconnu dans le catalogue',
	unreadable: 'Plaque illisible : saisissez le numéro de série',
	unknown: 'Appareil non trouvé',
	unavailable: 'Lecture indisponible : saisissez le numéro de série'
} as const;

/** What the panel shows when the appliance is not opened (`found` redirects to /sav/<serial>). */
export interface NameplateNotice {
	status: 'product' | 'unreadable' | 'unknown' | 'unavailable';
	message: string;
	serialNumber: string | null;
	reference: string | null;
	model: string | null;
	/** Catalog product (status `product`) and its sheet. */
	product: (NameplateProduct & { url: string }) | null;
	simulated: boolean;
	/** Link to /contact, prefilled (unknown appliance only). */
	contactUrl: string | null;
}

export type NameplateOutcome = { found: true; serialNumber: string } | ({ found: false } & NameplateNotice);

export function nameplateOutcome(reading: NameplateReading): NameplateOutcome {
	const serial = reading.serialNumber?.trim() || null;
	const reference = reading.reference?.trim() || null;
	if (reading.status === 'found' && serial) return { found: true, serialNumber: serial };
	const read = { serialNumber: serial, reference, model: reading.model, simulated: reading.simulated };
	if (reading.status === 'product' && reading.product?.slug) {
		return {
			found: false,
			status: 'product',
			message: NAMEPLATE_MESSAGES.product,
			...read,
			product: { ...reading.product, url: productUrl(reading.product.slug) },
			contactUrl: null
		};
	}
	if (reading.status === 'unknown' && (serial || reference)) {
		return { found: false, status: 'unknown', message: NAMEPLATE_MESSAGES.unknown, ...read, product: null, contactUrl: contactUrl(serial ?? reference ?? '') };
	}
	return { found: false, ...notice('unreadable', reading.simulated, reading.model) };
}

/** The reading service cannot answer now (API 503): never simulated. */
export const unavailableNotice = (): NameplateNotice => notice('unavailable', false, null);

function notice(status: 'unreadable' | 'unavailable', simulated: boolean, model: string | null): NameplateNotice {
	return { status, message: NAMEPLATE_MESSAGES[status], serialNumber: null, reference: null, model, product: null, simulated, contactUrl: null };
}

/** What was read, in one line: « n° YK23EVO06M009999, réf. YK-PAE-M06, Evolys monobloc 6 kW ». */
export function readOnPlate(read: Pick<NameplateNotice, 'serialNumber' | 'reference' | 'model'>): string {
	return [read.serialNumber && `n° ${read.serialNumber}`, read.reference && `réf. ${read.reference}`, read.model].filter(Boolean).join(', ');
}

export const productUrl = (slug: string): string => `/produit/${encodeURIComponent(slug)}`;

/** /contact with the subject prefilled (`?sujet=`, cut to the API limit by the contact page). */
export const contactUrl = (read: string): string => `/contact?sujet=${encodeURIComponent(`SAV : appareil non trouvé (${read})`)}`;

// ── Demo plates ──

/** Anchor of the panel on /sav (mobile tab bar). */
export const NAMEPLATE_ANCHOR = 'photo-plaque';

/** FICTIF plates of public/yack/demo/plaques/ (bin/yack-demo-plaques.php), one per case of the demo. */
export const DEMO_PLATES = [
	{ name: 'YK24HYD11S000321', label: 'YK24HYD11S000321', outcome: "ouvre l'appareil du parc" },
	{ name: 'SRK25ZS-W', label: 'Étiquette MHI SRK25ZS-W', outcome: 'propose la fiche produit' },
	{ name: 'YK23EVO06M009999', label: 'YK23EVO06M009999', outcome: '« Appareil non trouvé »' },
	{ name: 'illisible', label: 'Photo floue', outcome: '« Plaque illisible »' }
] as const;

export const demoPlateUrl = (name: string): string => `/yack/demo/plaques/${demoPlateFileName(name)}`;

/** File name of a demo plate, as downloaded and as sent when its link is clicked. */
export const demoPlateFileName = (name: string): string => `plaque-${encodeURIComponent(name)}-FICTIF.png`;

/** A demo plate: its name in public/yack/demo/plaques/, the text of its link, what it shows. */
export interface DemoPlate {
	name: string;
	label: string;
	outcome: string;
}
