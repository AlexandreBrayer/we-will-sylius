// Links of the blocks on the shop: `/connexion` and `/inscription` come back to the page shown after the login or
// the sign-up (#lib/account/returnPath.ts). The stored link does not change; the builder canvas keeps it as is.
import { withReturn } from '#lib/account/returnPath.js';
import { getYackContext } from './context.js';

/** Link of a block, already resolved (resolveLink) → link to put in `href`. Call it in the component's script. */
export function returnLinks(): (href: string) => string {
	const ctx = getYackContext();
	return (href) => (ctx.editor ? href : withReturn(href, ctx.returnPath));
}
