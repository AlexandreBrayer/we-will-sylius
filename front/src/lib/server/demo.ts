// Barre de démonstration : bascule en un clic sur les comptes de démo des fixtures
// (docs/POC_YACK.md). Active seulement si YACK_DEMO_BAR=1 ; sinon la barre n'est pas rendue et la bascule répond 404.
import { YACK_DEMO_BAR } from '$app/env/private';

export type DemoProfile = 'visiteur' | 'pro' | 'ecom';

/** Comptes de démo des fixtures (config/yack/fixtures.yaml), mot de passe commun des fixtures. */
export const DEMO_ACCOUNTS: Record<Exclude<DemoProfile, 'visiteur'>, string> = {
	pro: 'pro@yack.test',
	ecom: 'ecom@yack.test'
};
export const DEMO_PASSWORD = 'yack';

export const demoBarEnabled = (): boolean => YACK_DEMO_BAR === true;
