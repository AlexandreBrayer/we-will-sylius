import { syliusPublicUrl } from '$lib/server/sylius.js';
import type { LayoutServerLoad } from './$types';

export const load: LayoutServerLoad = async () => ({ syliusUrl: syliusPublicUrl() });
