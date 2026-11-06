import { redirect } from '@sveltejs/kit';
import type { RequestHandler } from './$types';

/** /commande → first checkout step (which sends further back when needed). */
export const GET: RequestHandler = () => redirect(303, '/commande/adresse');
