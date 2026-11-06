import adapter from '@sveltejs/adapter-node';
import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vite';

export default defineConfig({
	plugins: [
		sveltekit({
			compilerOptions: {
				runes: ({ filename }) =>
					filename.split(/[/\\]/).includes('node_modules') ? undefined : true
			},
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
