// « La pièce par une photo » (IA-05, page /sav): answer of POST /api/v2/shop/yack/sav/nameplate-readings
// and what the panel shows. Pure module without import: tested by `npm test` (test/nameplate.test.ts).

/** Status of the reading, as given by the API (`unavailable` is its 503 answer). */
export type NameplateStatus = 'found' | 'unreadable' | 'unknown';

/** POST yack/sav/nameplate-readings (multipart, field `photo`). */
export interface NameplateReading {
	status: NameplateStatus;
	/** Normalized serial number read on the plate (found or unknown), null when unreadable. */
	serialNumber: string | null;
	/** Model printed on the plate, as read. */
	model: string | null;
	/** Answer of the demo reader (YACK_AI_ADAPTER=fake). */
	simulated: boolean;
}

/** File field: same types in the `accept` of the input, the front check and the API (type read on the content). */
export const NAMEPLATE_ACCEPT = 'image/jpeg,image/png';
export const NAMEPLATE_EXTENSIONS = ['jpg', 'jpeg', 'png'] as const;

export const NAMEPLATE_MESSAGES = {
	unreadable: 'Plaque illisible : saisissez le numéro de série',
	unknown: 'Appareil non trouvé',
	unavailable: 'Lecture indisponible : saisissez le numéro de série'
} as const;

/** What the panel shows when the appliance is not opened (`found` redirects to /sav/<serial>). */
export interface NameplateNotice {
	status: 'unreadable' | 'unknown' | 'unavailable';
	message: string;
	serialNumber: string | null;
	model: string | null;
	simulated: boolean;
	/** Link to /contact, prefilled (unknown appliance only). */
	contactUrl: string | null;
}

export type NameplateOutcome = { found: true; serialNumber: string } | ({ found: false } & NameplateNotice);

export function nameplateOutcome(reading: NameplateReading): NameplateOutcome {
	const serial = reading.serialNumber?.trim() || null;
	if (reading.status === 'found' && serial) return { found: true, serialNumber: serial };
	if (reading.status === 'unknown' && serial) {
		return {
			found: false,
			status: 'unknown',
			message: NAMEPLATE_MESSAGES.unknown,
			serialNumber: serial,
			model: reading.model,
			simulated: reading.simulated,
			contactUrl: contactUrl(serial)
		};
	}
	return { found: false, ...notice('unreadable', reading.simulated, reading.model) };
}

/** The reading service cannot answer now (API 503): never simulated. */
export const unavailableNotice = (): NameplateNotice => notice('unavailable', false, null);

function notice(status: 'unreadable' | 'unavailable', simulated: boolean, model: string | null): NameplateNotice {
	return { status, message: NAMEPLATE_MESSAGES[status], serialNumber: null, model, simulated, contactUrl: null };
}

/** /contact with the subject prefilled (`?sujet=`, cut to the API limit by the contact page). */
export const contactUrl = (serial: string): string => `/contact?sujet=${encodeURIComponent(`SAV : appareil non trouvé (${serial})`)}`;

/** FICTIF demo plates (public/yack/demo/plaques/, bin/yack-demo-plaques.php): the four demo serials, plus one outside the installed base. */
export const DEMO_UNKNOWN_SERIAL = 'YK23EVO06M009999';

export const demoPlateUrl = (serial: string): string => `/yack/demo/plaques/plaque-${encodeURIComponent(serial)}-FICTIF.png`;
