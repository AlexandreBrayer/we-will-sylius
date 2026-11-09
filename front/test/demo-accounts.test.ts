// Demo accounts of the back-office (D16): each administrator of the fixtures that has a builder level
// (config/yack/fixtures.yaml, yack_builder_level) is listed with that level, under the back-office login
// form (templates/admin/yack/security/demo_accounts.html.twig) and in the demo bar (demo-profiles.ts).
// Labels of the levels: App\Yack\Content\Domain\BuilderLevel::label(). `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { DEMO_PASSWORD, DEMO_PROFILES, demoAccountLine } from '../src/lib/layout/demo-profiles.ts';

const root = new URL('../../', import.meta.url);
const read = (path: string): string => readFileSync(new URL(path, root), 'utf8');

/** Builder level of each administrator of the fixtures (email → level). */
function fixtureLevels(): Map<string, string> {
	const section = /\n {16}yack_builder_level:\n {20}options:\n {24}administrators:\n((?: {28}'[^']+': '[a-z_]+'\n)+)/.exec(read('config/yack/fixtures.yaml'));
	assert.ok(section, 'yack_builder_level dans fixtures.yaml');
	return new Map([...section[1].matchAll(/'([^']+)': '([a-z_]+)'/g)].map(([, email, level]) => [email, level]));
}

/** Label of each level (BuilderLevel: case value → label()). */
function levelLabels(): Map<string, string> {
	const php = read('src/Yack/Content/Domain/BuilderLevel.php');
	const values = new Map([...php.matchAll(/case (\w+) = '([a-z_]+)';/g)].map(([, name, value]) => [name, value]));
	return new Map([...php.matchAll(/self::(\w+) => '([^']+)',/g)].map(([, name, label]) => [values.get(name) ?? '', label]));
}

describe('demo accounts of the back-office', () => {
	const levels = fixtureLevels();
	const labels = levelLabels();

	it('the fixtures give admin@yack.test the Libre level and editeur@yack.test the Charte level, password yack', () => {
		assert.deepEqual([...levels], [
			['admin@yack.test', 'libre'],
			['editeur@yack.test', 'charte']
		]);
		assert.equal(labels.get('charte'), 'Charte');
		assert.equal(labels.get('libre'), 'Libre');
		const users = read('config/yack/fixtures.yaml');
		for (const email of levels.keys()) assert.match(users, new RegExp(`email: '${email}'\\n(?: {32}.+\\n)*? {32}password: '${DEMO_PASSWORD}'`), email);
	});

	it('the login page of the back-office lists every one with its level', () => {
		const twig = read('templates/admin/yack/security/demo_accounts.html.twig');
		const listed = [...twig.matchAll(/data-test-demo-account="([^"]+)" data-test-builder-level="([a-z_]+)">Identifiant : <code>\1<\/code> \(builder : niveau ([^)]+)\)/g)].map(
			([, email, level, label]) => [email, level, label]
		);
		assert.deepEqual(listed, [...levels].map(([email, level]) => [email, level, labels.get(level)]));
		assert.match(twig, new RegExp(`Mot de passe : <code>${DEMO_PASSWORD}</code>`));
	});

	it('the demo bar lists every one with its level, under the back-office profile', () => {
		const admin = DEMO_PROFILES.find((profile) => profile.key === 'admin');
		assert.ok(admin);
		const listed = [{ account: admin.account, note: admin.accountNote ?? '' }, ...(admin.otherAccounts ?? [])];
		assert.deepEqual(
			listed,
			[...levels].map(([account, level]) => ({ account, note: `builder : niveau ${labels.get(level)}` }))
		);
	});

	it('the demo bar writes each account as « account / password (level) », with its spaces', () => {
		const admin = DEMO_PROFILES.find((profile) => profile.key === 'admin');
		assert.ok(admin);
		assert.deepEqual(
			[demoAccountLine(admin.account, admin.accountNote), ...(admin.otherAccounts ?? []).map((other) => demoAccountLine(other.account, other.note))],
			['admin@yack.test / yack (builder : niveau Libre)', 'editeur@yack.test / yack (builder : niveau Charte)']
		);
		assert.equal(demoAccountLine('pro@yack.test'), 'pro@yack.test / yack');
	});
});
