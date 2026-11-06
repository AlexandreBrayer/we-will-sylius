import { searchListing } from '#lib/server/listing.js';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = (event) => searchListing(event);
