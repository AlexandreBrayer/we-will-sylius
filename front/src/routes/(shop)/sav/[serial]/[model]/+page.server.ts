import { error } from '@sveltejs/kit';
import { findModel, findSerial, savBreadcrumb } from '#lib/server/sav.js';
import { sylius } from '#lib/server/sylius.js';
import type { PageServerLoad } from './$types';

/** Exploded views of a model (ServiceCode) of the device searched. */
export const load: PageServerLoad = async (event) => {
	const { serial, model: serviceCode } = event.params;
	const api = sylius(event);
	const [device, model] = await Promise.all([findSerial(api, serial), findModel(api, serviceCode)]);
	if (!device || !model || !device.models.some((m) => m.serviceCode === model.serviceCode)) {
		error(404, 'Modèle introuvable pour cet appareil');
	}

	return { serial, model, breadcrumb: savBreadcrumb(serial, model) };
};
