// Photo of the rating plate, prepared in the browser before the upload (progressive enhancement: without
// JavaScript the form sends the file as chosen). Decoded by an <img> (EXIF orientation applied; Safari also
// decodes HEIC), reduced to PHOTO_MAX_SIDE and re-encoded in JPEG: a phone photo of 3 to 12 Mo leaves as a
// few hundred Ko, without its EXIF. Rules and messages: ./nameplate.ts.
import { PHOTO_JPEG_QUALITY, PHOTO_MESSAGES, jpegName, needsReduction, photoIssue, photoKind, reducedSize } from './nameplate.js';

export type PreparedPhoto = { file: File; reduced: boolean } | { error: string };

export async function preparePhoto(file: File): Promise<PreparedPhoto> {
	if (file.size === 0) return { error: PHOTO_MESSAGES.empty };

	const image = await decode(file);
	if (!image) {
		// Not decoded here: the file goes as is when the API reads it, never a HEIC or an unknown type.
		const issue = photoKind(file) === 'accepted' ? photoIssue(file) : photoKind(file) === 'heic' ? PHOTO_MESSAGES.heic : PHOTO_MESSAGES.format;
		return issue ? { error: issue } : { file, reduced: false };
	}

	try {
		if (!needsReduction(file, image.naturalWidth, image.naturalHeight)) return { file, reduced: false };

		const { width, height } = reducedSize(image.naturalWidth, image.naturalHeight);
		const canvas = document.createElement('canvas');
		canvas.width = width;
		canvas.height = height;
		const context = canvas.getContext('2d');
		if (!context) return fallback(file);
		context.drawImage(image, 0, 0, width, height);

		const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', PHOTO_JPEG_QUALITY));
		if (!blob || blob.size === 0) return fallback(file);
		return { file: new File([blob], jpegName(file.name), { type: 'image/jpeg', lastModified: Date.now() }), reduced: true };
	} finally {
		URL.revokeObjectURL(image.src);
	}
}

/** Canvas unavailable (memory of an old phone): the original when the API reads it. */
function fallback(file: File): PreparedPhoto {
	const issue = photoIssue(file);
	return issue ? { error: issue } : { file, reduced: false };
}

async function decode(file: File): Promise<HTMLImageElement | null> {
	const image = new Image();
	image.src = URL.createObjectURL(file);
	try {
		await image.decode();
		if (image.naturalWidth > 0 && image.naturalHeight > 0) return image;
		URL.revokeObjectURL(image.src);
		return null;
	} catch {
		URL.revokeObjectURL(image.src);
		return null;
	}
}

/**
 * Puts `file` in the file input, in place of what was chosen: the form (enhanced or not) sends it.
 * False when the browser cannot (DataTransfer missing): the original stays.
 */
export function replaceFile(input: HTMLInputElement, file: File): boolean {
	try {
		const transfer = new DataTransfer();
		transfer.items.add(file);
		input.files = transfer.files;
		return input.files?.[0] === file || input.files?.[0]?.name === file.name;
	} catch {
		return false;
	}
}
