// Media library of the builder (D7): images of GET /api/v2/admin/cms-media, the rules of a file sent to it
// (the same in the browser, in the check of the remote form and, as messages, in the API: CmsMediaFile) and
// what the window shows. Pure module without import: tested by `npm test` (test/media.test.ts).

/** Image of the library (CmsMediaView of the API). */
export interface MediaItem {
	id: number;
	/** Value of an image prop: `media/yack-cms/<32 hex>.<ext>`, or `yack/...` for a historical visual. */
	path: string;
	/** Whole image (`/` + path, served by nginx). */
	url: string;
	/** WebP of 400 px at most (LiipImagine, yack_cms_media_thumb). */
	thumbnail: string;
	/** Original name, cleaned by the API. */
	name: string;
	mediaType: string;
	width: number;
	height: number;
	/** Bytes. */
	size: number;
	/** Default alternative text ('' when none). */
	alt: string;
	/** « Prénom Nom » of the administrator who sent it; null for a historical visual. */
	author: string | null;
	createdAt: string;
	/** false: historical visual of the site (its file belongs to the repository). */
	uploaded: boolean;
}

/** Largest file: CmsMediaFile::MAX_BYTES (20 Mio), as UPLOAD_MAX_BYTES (#lib/server/upload.ts). */
export const MEDIA_MAX_BYTES = 20 * 1024 * 1024;

/** Longest default alternative text (CmsMediaFile). */
export const MEDIA_ALT_MAX = 255;

/** `accept` of the file input. */
export const MEDIA_ACCEPT = 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp';

const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

/** Messages of the API (CmsMediaFile, violations of POST /cms-media), shown as they are. */
export const MEDIA_MESSAGES = {
	missing: 'Choisissez un fichier image.',
	empty: 'Le fichier est vide.',
	tooLarge: 'Fichier trop volumineux (20 Mo maximum).',
	svg: 'Les images SVG ne sont pas acceptées : envoyez un JPEG, un PNG ou un WebP.',
	format: 'Format non accepté : JPEG, PNG ou WebP uniquement.',
	altTooLong: 'Le texte alternatif fait au plus 255 caractères.'
} as const;

function extensionOf(name: string): string {
	const dot = name.lastIndexOf('.');
	return dot <= 0 || dot === name.length - 1 ? '' : name.slice(dot + 1).toLowerCase();
}

/**
 * Why the file cannot be sent (French message of the API), null when it may be: not empty, 20 Mo at most,
 * a JPEG, PNG or WebP by its name or its type. The API reads the type on the content and stays the judge.
 */
export function mediaIssue(file: { name: string; type: string; size: number }): string | null {
	const extension = extensionOf(file.name);
	if (extension === 'svg' || file.type === 'image/svg+xml') return MEDIA_MESSAGES.svg;
	if (!EXTENSIONS.includes(extension) && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return MEDIA_MESSAGES.format;
	if (file.size === 0) return MEDIA_MESSAGES.empty;
	if (file.size > MEDIA_MAX_BYTES) return MEDIA_MESSAGES.tooLarge;
	return null;
}

const text = (value: unknown): string => (typeof value === 'string' ? value : '');
const count = (value: unknown): number => (typeof value === 'number' && Number.isFinite(value) && value >= 0 ? value : 0);

/** One image of the API, null when it is not one (no id or path). */
export function parseMediaItem(raw: unknown): MediaItem | null {
	if (typeof raw !== 'object' || raw === null) return null;
	const source = raw as Record<string, unknown>;
	const path = text(source.path);
	if (typeof source.id !== 'number' || path === '') return null;
	return {
		id: source.id,
		path,
		url: text(source.url) || `/${path}`,
		thumbnail: text(source.thumbnail) || text(source.url) || `/${path}`,
		name: text(source.name) || path.slice(path.lastIndexOf('/') + 1),
		mediaType: text(source.mediaType),
		width: count(source.width),
		height: count(source.height),
		size: count(source.size),
		alt: text(source.alt),
		author: typeof source.author === 'string' && source.author !== '' ? source.author : null,
		createdAt: text(source.createdAt),
		uploaded: source.uploaded !== false
	};
}

/** The list of GET /cms-media (entries that are not images left out). */
export function parseMediaList(raw: unknown): MediaItem[] {
	return Array.isArray(raw) ? raw.map(parseMediaItem).filter((item): item is MediaItem => item !== null) : [];
}

/** « 845 Ko », « 1,2 Mo » (1 Ko = 1 024 octets, as the 20 Mo of the limit). */
export function formatBytes(bytes: number): string {
	if (bytes < 1024) return `${bytes} octets`;
	if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} Ko`;
	return `${(bytes / (1024 * 1024)).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} Mo`;
}

/** « 1 920 × 1 080 px ». */
export function formatDimensions(item: Pick<MediaItem, 'width' | 'height'>): string {
	return `${item.width.toLocaleString('fr-FR')} × ${item.height.toLocaleString('fr-FR')} px`;
}

/** Share of the batch sent (0 to 1): bytes of the files done (sent or refused) over the bytes of the batch. */
export function batchProgress(files: { size: number; done: boolean }[]): number {
	const total = files.reduce((sum, file) => sum + Math.max(file.size, 1), 0);
	if (total === 0) return 0;
	return files.reduce((sum, file) => sum + (file.done ? Math.max(file.size, 1) : 0), 0) / total;
}
