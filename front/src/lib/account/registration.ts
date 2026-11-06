// Yack 4-step sign-up (port of the Twig form, src/Yack/Account/Infrastructure/Form/Registration/*):
// field names, per-step validation (same French messages as the API) and mapping of the API 422
// violations (POST /api/v2/shop/yack/registrations) back to the form fields.
// Shared by the browser (instant step changes) and the server (no-JS fallback, final submit).

export const STEPS = [
	{ name: 'profil', label: 'Profil' },
	{ name: 'societe', label: 'Société' },
	{ name: 'contact', label: 'Contact' },
	{ name: 'adresses', label: 'Adresses' }
] as const;

export const STEP_COUNT: number = STEPS.length;

export const FIELDS = [
	'profile',
	'companyName',
	'siret',
	'vatNumber',
	'lastName',
	'firstName',
	'email',
	'phone',
	'password',
	'passwordConfirm',
	'billingStreet',
	'billingPostcode',
	'billingCity',
	'billingCountryCode',
	'shippingSame',
	'shippingStreet',
	'shippingPostcode',
	'shippingCity',
	'shippingCountryCode'
] as const;

export type Field = (typeof FIELDS)[number];
export type RegistrationValues = Record<Field, string>;
export type FieldErrors = Partial<Record<Field, string>>;

/** Step (1-based) holding each field. */
const FIELD_STEP: Record<Field, number> = {
	profile: 1,
	companyName: 2,
	siret: 2,
	vatNumber: 2,
	lastName: 3,
	firstName: 3,
	email: 3,
	phone: 3,
	password: 3,
	passwordConfirm: 3,
	billingStreet: 4,
	billingPostcode: 4,
	billingCity: 4,
	billingCountryCode: 4,
	shippingSame: 4,
	shippingStreet: 4,
	shippingPostcode: 4,
	shippingCity: 4,
	shippingCountryCode: 4
};

/** API violation path → form field. */
const VIOLATION_FIELDS: Record<string, Field> = {
	profile: 'profile',
	'company.name': 'companyName',
	'company.siret': 'siret',
	'company.vatNumber': 'vatNumber',
	'contact.firstName': 'firstName',
	'contact.lastName': 'lastName',
	'contact.email': 'email',
	'contact.phone': 'phone',
	'contact.password': 'password',
	'billingAddress.street': 'billingStreet',
	'billingAddress.postcode': 'billingPostcode',
	'billingAddress.city': 'billingCity',
	'billingAddress.countryCode': 'billingCountryCode',
	'shippingAddress.street': 'shippingStreet',
	'shippingAddress.postcode': 'shippingPostcode',
	'shippingAddress.city': 'shippingCity',
	'shippingAddress.countryCode': 'shippingCountryCode'
};

export function emptyValues(): RegistrationValues {
	const values = Object.fromEntries(FIELDS.map((field) => [field, ''])) as RegistrationValues;
	values.billingCountryCode = 'FR';
	values.shippingCountryCode = 'FR';
	values.shippingSame = '1';
	return values;
}

/** Values of a submitted form (unchecked "Livrer à cette adresse" = ''). */
export function readValues(form: FormData): RegistrationValues {
	const values = emptyValues();
	for (const field of FIELDS) values[field] = String(form.get(field) ?? '').trim();
	// Passwords are kept as typed.
	values.password = String(form.get('password') ?? '');
	values.passwordConfirm = String(form.get('passwordConfirm') ?? '');
	return values;
}

const normalizeSiret = (siret: string) => siret.replace(/[\s.-]/g, '');

/** SIRET: 14 digits + Luhn key (La Poste establishments: sum of digits multiple of 5) — Domain\Siret. */
export function isValidSiret(value: string): boolean {
	const siret = normalizeSiret(value);
	if (!/^\d{14}$/.test(siret)) return false;
	const digits = [...siret].map(Number);
	if (siret.startsWith('356000000') && siret !== '35600000000048') {
		return digits.reduce((sum, digit) => sum + digit, 0) % 5 === 0;
	}
	const sum = digits.reverse().reduce((total, digit, position) => {
		const value = position % 2 === 1 ? digit * 2 : digit;
		return total + (value > 9 ? value - 9 : value);
	}, 0);
	return sum % 10 === 0;
}

/** French VAT number: FR + 2-char key + SIREN (= 9 first SIRET digits), numeric key checked. */
export function isValidVatNumber(value: string, siret: string): boolean {
	const match = /^FR([0-9A-Z]{2})(\d{9})$/.exec(normalizeSiret(value).toUpperCase());
	if (!match) return false;
	const [, key, siren] = match;
	if (siret && !normalizeSiret(siret).startsWith(siren)) return false;
	if (/^\d{2}$/.test(key)) return Number(key) === (12 + 3 * (Number(siren) % 97)) % 97;
	return true;
}

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PHONE = /^\+?[0-9 .()-]{10,20}$/;

function validateAddress(values: RegistrationValues, prefix: 'billing' | 'shipping', errors: FieldErrors): void {
	if (!values[`${prefix}Street`]) errors[`${prefix}Street`] = "Indiquez l'adresse (numéro et voie).";
	if (!values[`${prefix}Postcode`]) errors[`${prefix}Postcode`] = 'Indiquez le code postal.';
	else if (values[`${prefix}Postcode`].length > 10) errors[`${prefix}Postcode`] = 'Code postal trop long.';
	if (!values[`${prefix}City`]) errors[`${prefix}City`] = 'Indiquez la ville.';
	if (!values[`${prefix}CountryCode`]) errors[`${prefix}CountryCode`] = 'Choisissez un pays.';
}

/** Errors of one step (1-based); empty object when the step is valid. */
export function validateStep(step: number, values: RegistrationValues): FieldErrors {
	const errors: FieldErrors = {};
	switch (step) {
		case 1:
			if (values.profile !== 'pro' && values.profile !== 'ecom') errors.profile = 'Choisissez votre profil.';
			break;
		case 2:
			if (values.profile === 'pro' && !values.companyName) errors.companyName = 'Indiquez la raison sociale de votre entreprise.';
			if (values.companyName.length > 255) errors.companyName = 'La raison sociale ne doit pas dépasser 255 caractères.';
			if (!values.siret) {
				if (values.profile === 'pro') errors.siret = 'Le SIRET est obligatoire pour un compte professionnel.';
			} else if (!isValidSiret(values.siret)) {
				errors.siret = 'Le SIRET doit comporter 14 chiffres valides (vérifiez la saisie, clé de contrôle incorrecte).';
			}
			if (values.vatNumber && !isValidVatNumber(values.vatNumber, values.siret)) {
				errors.vatNumber = 'N° de TVA invalide : format FR + clé (2 caractères) + SIREN (9 premiers chiffres du SIRET).';
			}
			break;
		case 3:
			if (!values.lastName) errors.lastName = 'Indiquez votre nom.';
			if (!values.firstName) errors.firstName = 'Indiquez votre prénom.';
			if (!values.email) errors.email = 'Indiquez votre adresse e-mail.';
			else if (!EMAIL.test(values.email)) errors.email = "Cette adresse e-mail n'est pas valide.";
			if (!values.phone) errors.phone = 'Indiquez un numéro de téléphone.';
			else if (!PHONE.test(values.phone)) errors.phone = 'Numéro de téléphone invalide (10 chiffres attendus).';
			if (!values.password) errors.password = 'Choisissez un mot de passe.';
			else if (values.password.length < 8) errors.password = 'Le mot de passe doit comporter au moins 8 caractères.';
			else if (values.password !== values.passwordConfirm) errors.passwordConfirm = 'Les deux mots de passe ne correspondent pas.';
			break;
		case 4:
			validateAddress(values, 'billing', errors);
			if (values.shippingSame !== '1') validateAddress(values, 'shipping', errors);
			break;
	}
	return errors;
}

/** First invalid step up to `upTo` (inclusive) with its errors, or null when all are valid. */
export function firstInvalidStep(values: RegistrationValues, upTo = STEP_COUNT): { step: number; errors: FieldErrors } | null {
	for (let step = 1; step <= upTo; step++) {
		const errors = validateStep(step, values);
		if (Object.keys(errors).length > 0) return { step, errors };
	}
	return null;
}

/** Errors of the API 422 mapped to the fields, and the earliest step to show. */
export function violationErrors(violations: { propertyPath: string; message: string }[]): { step: number; errors: FieldErrors; global: string[] } {
	const errors: FieldErrors = {};
	const global: string[] = [];
	let step = STEP_COUNT;
	for (const violation of violations) {
		const field = VIOLATION_FIELDS[violation.propertyPath];
		if (!field) {
			global.push(violation.message);
			continue;
		}
		errors[field] ??= violation.message;
		step = Math.min(step, FIELD_STEP[field]);
	}
	return { step, errors, global };
}

/** Body of POST /api/v2/shop/yack/registrations. */
export function registrationPayload(values: RegistrationValues) {
	const address = (prefix: 'billing' | 'shipping') => ({
		street: values[`${prefix}Street`],
		postcode: values[`${prefix}Postcode`],
		city: values[`${prefix}City`],
		countryCode: values[`${prefix}CountryCode`]
	});
	const orNull = (value: string) => value || null;
	return {
		profile: values.profile,
		company: { name: orNull(values.companyName), siret: orNull(values.siret), vatNumber: orNull(values.vatNumber) },
		contact: {
			firstName: values.firstName,
			lastName: values.lastName,
			email: values.email,
			phone: values.phone,
			password: values.password
		},
		billingAddress: address('billing'),
		shippingAddress: values.shippingSame === '1' ? null : address('shipping')
	};
}
