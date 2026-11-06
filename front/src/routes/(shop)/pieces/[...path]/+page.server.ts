import { sectionListing } from '#lib/server/listing.js';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = (event) => sectionListing(event, '/pieces', event.params.path);
