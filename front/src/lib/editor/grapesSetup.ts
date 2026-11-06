// GrapesJS set-up adapted from sveltekit-builder, locked to the Yack charte:
//  - only Yack blocks (no basic / raw HTML / code blocks, no import);
//  - no style manager, selector manager or layer panel: editors edit props (traits) only;
//  - blocks are atomic (not droppable, not editable inline) and can only sit at page level;
//  - the canvas loads the Sylius shop CSS (same origin, behind nginx), so what you see is what the shop renders.
import grapesjs, { type Component as GjsComponent, type Editor, type ProjectData } from 'grapesjs';
import { mount, unmount, type Component } from 'svelte';
import { COMPONENTS } from '#lib/blocks/components.js';
import { yackContextMap } from '#lib/blocks/context.js';
import { VISITOR } from '#lib/types.js';
import { BLOCK_TYPES, REGISTRY, pickProps } from '#lib/blocks/registry.js';
import type { BlockProps, BlockType, CmsPage, PageBlock } from '#lib/blocks/types.js';

type Mounted = { instance: Record<string, unknown>; handler: () => void };

const isBlockType = (type: unknown): type is BlockType => BLOCK_TYPES.includes(type as BlockType);

/**
 * View rendering a Svelte block in the canvas, re-mounted whenever its attributes (props) change.
 * Rendered as a visitor would see it (no prices).
 */
function svelteView(component: Component<BlockProps>) {
	const context = yackContextMap({ viewer: VISITOR, catalog: null, editor: true });
	const mounted = new WeakMap<HTMLElement, Mounted>();

	const render = (el: HTMLElement, model: GjsComponent): (() => void) => {
		const current = mounted.get(el);
		if (current) unmount(current.instance);
		el.innerHTML = '';
		const instance = mount(component, { target: el, props: pickProps(model.get('type') ?? '', model.getAttributes()), context });
		const handler: () => void = current?.handler ?? (() => render(el, model));
		mounted.set(el, { instance, handler });
		return handler;
	};

	return {
		onRender({ el, model }: { el: HTMLElement; model: GjsComponent }) {
			const handler = render(el, model);
			model.off('change:attributes', handler);
			model.on('change:attributes', handler);
		},
		removed(this: { el: HTMLElement; model: GjsComponent }) {
			const current = mounted.get(this.el);
			if (!current) return;
			unmount(current.instance);
			this.model.off('change:attributes', current.handler);
			mounted.delete(this.el);
		}
	};
}

export interface EditorOptions {
	container: HTMLElement;
	blocksEl: HTMLElement;
	traitsEl: HTMLElement;
	page: CmsPage;
}

export function initEditor({ container, blocksEl, traitsEl, page }: EditorOptions): Editor {
	const { origin } = window.location;
	const editor = grapesjs.init({
		container,
		height: '100%',
		width: 'auto',
		storageManager: false,
		panels: { defaults: [] },
		canvas: {
			styles: [`${origin}/build/shop/shop-entry.css`, `${origin}/build/app/shop/app-shop-entry.css`]
		},
		blockManager: { appendTo: blocksEl },
		traitManager: { appendTo: traitsEl },
		styleManager: { sectors: [] },
		layerManager: { appendTo: document.createElement('div') },
		selectorManager: { componentFirst: true },
		deviceManager: {
			devices: [
				{ id: 'desktop', name: 'Ordinateur', width: '' },
				{ id: 'tablet', name: 'Tablette', width: '820px', widthMedia: '992px' },
				{ id: 'mobile', name: 'Mobile', width: '390px', widthMedia: '576px' }
			]
		},
		richTextEditor: { actions: [] },
		assetManager: { upload: false }
	});

	editor.Traits.addType('textarea', {
		createInput({ trait }) {
			const input = document.createElement('textarea');
			input.rows = 5;
			input.placeholder = trait.get('placeholder') ?? '';
			return input;
		},
		onEvent({ elInput, component, trait }) {
			component.addAttributes({ [trait.getName()]: elInput.value });
		},
		onUpdate({ elInput, component, trait }) {
			elInput.value = component.getAttributes()[trait.getName()] ?? '';
		}
	});

	for (const entry of REGISTRY) {
		editor.DomComponents.addType(entry.type, {
			model: {
				defaults: {
					tagName: 'div',
					name: entry.label,
					// Props live in attributes (read by pickProps on save) — not as model props.
					attributes: { ...entry.defaults },
					traits: entry.traits.map((trait) => ({ ...trait, changeProp: false })),
					droppable: false,
					editable: false,
					stylable: false,
					resizable: false,
					highlightable: true,
					layerable: false,
					draggable: (_source: GjsComponent, target: GjsComponent) => target.get('type') === 'wrapper'
				}
			},
			view: svelteView(COMPONENTS[entry.type])
		});

		editor.Blocks.add(entry.type, {
			label: `<strong>${entry.label}</strong><small>${entry.description}</small>`,
			media: entry.icon,
			category: 'Blocs Yack',
			content: { type: entry.type },
			// Click = append at the end of the page (in addition to drag & drop).
			onClick: (_block, ed) => {
				const [added] = ed.getWrapper()!.append({ type: entry.type });
				ed.select(added);
				added.getEl()?.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		});
	}

	const grapes = page.grapesData;
	if (grapes && Array.isArray(grapes.pages) && grapes.pages.length > 0) {
		editor.loadProjectData(grapes as ProjectData);
	} else {
		editor.getWrapper()!.components(page.blocks.map((block) => ({ type: block.type, attributes: { ...block.props } })));
	}

	// After loading: the project data would otherwise restore a default wrapper.
	const wrapper = editor.getWrapper()!;
	wrapper.set({ stylable: false, droppable: (source: GjsComponent) => isBlockType(source.get('type')) });

	editor.on('load', () => {
		const doc = editor.Canvas.getDocument();
		if (doc) {
			const style = doc.createElement('style');
			style.textContent = 'body{padding-bottom:4rem;min-height:100vh}a{pointer-events:none}';
			doc.head.appendChild(style);
		}
		const first = wrapper.components().at(0);
		if (first) editor.select(first);
	});

	return editor;
}

/** Page content to save: the ordered blocks (rendering source) + the GrapesJS project. */
export function collectPage(editor: Editor): { blocks: PageBlock[]; grapesData: Record<string, unknown> } {
	const blocks: PageBlock[] = [];
	editor
		.getWrapper()!
		.components()
		.forEach((component: GjsComponent) => {
			const type: unknown = component.get('type');
			if (isBlockType(type)) blocks.push({ type, props: pickProps(type, component.getAttributes()) });
		});

	return { blocks, grapesData: editor.getProjectData() };
}
