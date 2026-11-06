import { redirect } from '@sveltejs/kit';

// The CMS "accueil" page is also the Sylius shop homepage (rendered in Twig there).
export const load = () => redirect(307, '/accueil');
