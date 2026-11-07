// Files sent by a remote form, checked before the Sylius API call (docs/adr/0005-fichiers-televerses.md).
// This check only spares a useless call and gives a French message: the API stays the judge
// (type read from the content, final size, rights). Pure module: tested by `npm test`.
import * as v from 'valibot';

/** Largest file accepted: same value as nginx (client_max_body_size 20m) and BODY_SIZE_LIMIT (compose.yml). */
export const UPLOAD_MAX_BYTES = 20 * 1024 * 1024;

export const UPLOAD_TOO_LARGE = 'Fichier trop volumineux (20 Mo maximum)';
export const UPLOAD_EMPTY = 'Le fichier est vide.';

/** Extensions accepted by a use case, lower case without the dot (e.g. `['pdf', 'jpg', 'jpeg', 'png']`). */
export type UploadExtensions = readonly string[];

/** Extension of a file name, lower case without the dot; '' when there is none. */
export function extensionOf(name: string): string {
	const dot = name.lastIndexOf('.');
	return dot <= 0 || dot === name.length - 1 ? '' : name.slice(dot + 1).toLowerCase();
}

function formatNotAccepted(extensions: UploadExtensions): string {
	return `Format non accepté (${extensions.map((e) => e.toUpperCase()).join(', ')}).`;
}

/** French message explaining why the file is refused, `null` when it may be sent to the API. */
export function uploadIssue(file: Pick<File, 'name' | 'size'>, extensions: UploadExtensions, maxBytes = UPLOAD_MAX_BYTES): string | null {
	if (file.size === 0) return UPLOAD_EMPTY;
	if (file.size > maxBytes) return UPLOAD_TOO_LARGE;
	if (!extensions.includes(extensionOf(file.name))) return formatNotAccepted(extensions);
	return null;
}

/**
 * Valibot schema of a file field of a remote form, with the rules of `uploadIssue`:
 * `form(v.object({ kbis: uploadField(['pdf', 'jpg', 'jpeg', 'png']) }), …)`.
 */
export function uploadField(extensions: UploadExtensions, maxBytes = UPLOAD_MAX_BYTES) {
	return v.pipe(
		v.file('Fichier obligatoire.'),
		v.minSize(1, UPLOAD_EMPTY),
		v.maxSize(maxBytes, UPLOAD_TOO_LARGE),
		v.check((file) => extensions.includes(extensionOf(file.name)), formatNotAccepted(extensions))
	);
}

/**
 * Multipart body for `ShopClient.postForm()`: text fields, then the files under their field name.
 * The original file name is sent for information only: the API stores the file under a random name.
 */
export function uploadFormData(fields: Record<string, string>, files: Record<string, File>): FormData {
	const body = new FormData();
	for (const [name, value] of Object.entries(fields)) body.append(name, value);
	for (const [name, file] of Object.entries(files)) body.append(name, file, file.name);
	return body;
}
