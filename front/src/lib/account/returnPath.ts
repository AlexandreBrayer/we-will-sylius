// Retour sur la page d'origine après une connexion ou une inscription (`?redirect=`). Module pur, partagé par le
// navigateur et le serveur : le serveur applique `safeReturnPath` avant toute redirection (#lib/server/session.ts).

export const LOGIN_PATH = '/connexion';
export const SIGNUP_PATH = '/inscription';
/** Paramètre de la page d'origine, sur /connexion et /inscription. */
export const RETURN_PARAM = 'redirect';
/** Paramètre du profil présélectionné sur /inscription (`pro` ou `ecom`). */
export const PROFILE_PARAM = 'profil';

const MAX_RETURN_LENGTH = 2000;

/**
 * `target` quand c'est un chemin du site, `null` sinon (redirection ouverte). Accepté : commence par un seul `/`,
 * sans `\` ni caractère de contrôle ou espace (le navigateur retire tabulations et retours à la ligne d'une URL :
 * `/<tab>/hote` deviendrait `//hote`, une adresse d'un autre site), 2 000 caractères au plus.
 */
export function safeReturnPath(target: string | null | undefined): string | null {
	if (typeof target !== 'string' || target.length > MAX_RETURN_LENGTH) return null;
	if (!target.startsWith('/') || target.startsWith('//')) return null;
	// eslint-disable-next-line no-control-regex
	if (/[\\\u0000- \u007f]/.test(target)) return null;
	return target;
}

/** Profil présélectionné de /inscription : `pro`, `ecom`, ou '' (au choix du visiteur). */
export function presetProfile(value: string | null | undefined): 'pro' | 'ecom' | '' {
	return value === 'pro' || value === 'ecom' ? value : '';
}

/**
 * Lien d'un bloc de page CMS : `/connexion` et `/inscription` (format stocké inchangé) emmènent vers la page
 * demandée avec le retour sur `here`, la page affichée. `/inscription` présélectionne en plus le profil PRO : dans
 * le builder, ce lien s'appelle « Créer un compte PRO » (#lib/editor/traits/link.ts). Un lien qui porte déjà un
 * retour ou un profil le garde ; tout autre lien, ou un `here` refusé par `safeReturnPath`, ressort tel quel.
 */
export function withReturn(href: string, here: string | null | undefined): string {
	const back = safeReturnPath(here);
	if (!back) return href;
	const match = /^(\/[^?#]*)(\?[^#]*)?(#.*)?$/.exec(href);
	if (!match) return href;
	const [, path, query = '', hash = ''] = match;
	if (path !== LOGIN_PATH && path !== SIGNUP_PATH) return href;
	// Pas de retour vers la page de connexion ou d'inscription elle-même.
	const backPath = back.split(/[?#]/)[0];
	if (backPath === LOGIN_PATH || backPath === SIGNUP_PATH || backPath.startsWith(`${SIGNUP_PATH}/`)) return href;

	const params = new URLSearchParams(query);
	if (path === SIGNUP_PATH && !params.has(PROFILE_PARAM)) params.set(PROFILE_PARAM, 'pro');
	if (!params.has(RETURN_PARAM)) params.set(RETURN_PARAM, back);
	return `${path}?${params}${hash}`;
}
