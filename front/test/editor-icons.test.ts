// Pictogrammes de l'éditeur (E3) : @lucide/svelte n'entre dans le front que par front/src/lib/editor/icons.ts, qui
// réexporte une liste fermée d'icônes, une par fichier du paquet. Sources lues comme des fichiers, `npm test`.
import assert from 'node:assert/strict';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { describe, it } from 'node:test';

const root = new URL('../', import.meta.url);
const read = (path: string): string => readFileSync(new URL(path, root), 'utf8');
const ICONS = 'src/lib/editor/icons.ts';

/** Liste fermée : nom exporté => fichier de @lucide/svelte/icons. Une icône nouvelle s'ajoute ici et dans icons.ts. */
const CLOSED_LIST: Record<string, string> = {
	RotateCcw: 'rotate-ccw',
	Lock: 'lock',
	TriangleAlert: 'triangle-alert',
	Info: 'info',
	BookmarkPlus: 'bookmark-plus',
	X: 'x',
	GripVertical: 'grip-vertical',
	ArrowUp: 'arrow-up',
	ArrowDown: 'arrow-down',
	Trash2: 'trash-2',
	Plus: 'plus',
	Search: 'search',
	CircleHelp: 'circle-help',
	ChevronDown: 'chevron-down'
};

/** Fichiers source du front (TypeScript, JavaScript, Svelte), chemins relatifs à front/. */
function sources(dir = 'src'): string[] {
	return readdirSync(new URL(dir, root)).flatMap((name) => {
		const path = `${dir}/${name}`;
		if (statSync(new URL(path, root)).isDirectory()) return sources(path);
		return /\.(ts|js|svelte)$/.test(name) ? [path] : [];
	});
}

/** Modules importés par un fichier : `import … from '…'`, `export … from '…'`, `import('…')`. */
function imports(text: string): string[] {
	return [...text.matchAll(/(?:\bfrom\s*|\bimport\s*\(?\s*)['"]([^'"]+)['"]/g)].map((m) => m[1]);
}

const isLucide = (module: string): boolean => /^(?:@lucide\/svelte|lucide-svelte)(?:\/|$)/.test(module);

describe("icônes de l'éditeur", () => {
	it("n'importent @lucide/svelte (ni lucide-svelte) que dans icons.ts", () => {
		const files = sources();
		assert.ok(files.some((path) => path.startsWith('src/lib/editor/')), "les fichiers de l'éditeur sont lus");
		const offenders = files.filter((path) => path !== ICONS && imports(read(path)).some(isLucide));
		assert.deepEqual(offenders, []);
	});

	it('réexportent exactement la liste fermée, une icône par fichier du paquet', () => {
		const text = read(ICONS);
		const exported = [...text.matchAll(/^export\s*\{\s*default as (\w+)\s*\}\s*from\s*'([^']+)';$/gm)].map((m) => [m[1], m[2]]);
		assert.deepEqual(
			imports(text).filter((module) => !isLucide(module)),
			[],
			'icons.ts ne lit rien d’autre que @lucide/svelte'
		);
		assert.equal(imports(text).length, exported.length, 'chaque import est une réexportation « default as Nom »');
		assert.deepEqual(Object.fromEntries(exported.map(([name, module]) => [name, module])), Object.fromEntries(Object.entries(CLOSED_LIST).map(([name, file]) => [name, `@lucide/svelte/icons/${file}`])));
	});

	it('fixent la version du paquet (seule dépendance npm ajoutée par le chantier)', () => {
		const pkg = JSON.parse(read('package.json')) as { dependencies?: Record<string, string>; devDependencies?: Record<string, string> };
		const all = { ...pkg.dependencies, ...pkg.devDependencies };
		assert.match(all['@lucide/svelte'] ?? '', /^\d+\.\d+\.\d+$/, 'version exacte de @lucide/svelte');
		assert.equal(all['lucide-svelte'], undefined, 'lucide-svelte (déprécié) n’est pas installé à côté');
		const lock = JSON.parse(read('package-lock.json')) as { packages: Record<string, { version?: string }> };
		assert.equal(lock.packages['node_modules/@lucide/svelte']?.version, all['@lucide/svelte'], 'package-lock.json suit package.json');
	});
});
