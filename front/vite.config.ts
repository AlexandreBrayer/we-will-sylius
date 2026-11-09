import adapter from '@sveltejs/adapter-node';
import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vite';

export default defineConfig({
	plugins: [
		sveltekit({
			compilerOptions: {
				runes: ({ filename }) =>
					filename.split(/[/\\]/).includes('node_modules') ? undefined : true,
				// `await` in components (markup, `$derived(await …)`): see front/README.md « Remote functions ».
				experimental: { async: true }
			},
			// `#lib/remote/*.remote.ts` (query / form / command) replace load functions and form actions.
			experimental: { remoteFunctions: true },
			// SSR on node (compose service « front », behind nginx); `npm run build && node build`.
			adapter: adapter()
		})
	],
	server: {
		host: true,
		port: 3000,
		strictPort: true
	}
});
