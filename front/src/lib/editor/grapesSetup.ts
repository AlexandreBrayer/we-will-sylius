// GrapesJS set-up adapted from sveltekit-builder, locked to the Yack charte:
//  - only Yack blocks (no basic / raw HTML / code blocks, no import): free blocks for a page, plus
//    the bound blocks of its type for a template — whose mandatory block is always present, cannot
//    be removed nor duplicated (model prop `yackMandatory`, shown as a padlock by the tree);
//  - no style manager, selector manager or layer panel: editors edit props and the named style of the
//    block (traits, rendered by panels/BlockPanel.svelte in the tabs Contenu, Style, Avancé); the style
//    lives in the attributes `yb-<property>` (panels/blockStyle.ts), next to the props;
//  - blocks are atomic (not droppable, no GrapesJS rich text editor) and can only sit at page level;
//    their texts are typed in place through the block's own text editing (D8, inline/textEditing.ts);
//  - the canvas loads the Sylius shop CSS (same origin, behind nginx), so what you see is what the shop renders.
import grapesjs, { type Component as GjsComponent, type Editor, type ProjectData } from 'grapesjs';
import { mount, unmount } from 'svelte';
import BlockRenderer from '#lib/blocks/BlockRenderer.svelte';
import { yackContextMap, type YackContext } from '#lib/blocks/context.js';
import { allowedBlocks, blockDefinition, REGISTRY, pickProps, STYLE_TRAITS } from '#lib/blocks/registry.js';
import type { BlockType, PageBlock, TemplateType } from '#lib/blocks/types.js';
import { pickStyle, styleAttributeChanges, styleAttributes, type BuilderMe } from '#lib/editor/panels/blockStyle.js';
import { registerTraits } from '#lib/editor/traits/registerTraits.js';
import { createTextEditing, type TextEditingController } from '#lib/editor/inline/textEditing.js';

type Mounted = { instance: Record<string, unknown>; handler: () => void; editing: TextEditingController };

const isBlockType = (type: unknown): type is BlockType => typeof type === 'string' && blockDefinition(type) !== undefined;

/** Block of the component as it is saved: props, and the style when one is set. */
function blockOf(type: BlockType, attributes: Record<string, unknown>): PageBlock {
	const style = pickStyle(attributes);
	return { type, props: pickProps(type, attributes), ...(Object.keys(style).length > 0 ? { style } : {}) };
}

/**
 * View rendering a block in the canvas through BlockRenderer (same wrapper and style as the shop,
 * always wrapped in the editor), re-mounted whenever its attributes (props, style) change, except
 * while one of its texts is typed in place (the change comes from the typing: the text editing keeps
 * the element and its caret, inline/textEditing.ts).
 * `context`: visitor viewer, editor loaders and, for a template, the sample product / category
 * (reactive getters: bound blocks follow the sample picked in the builder).
 */
function svelteView(type: BlockType, yackContext: YackContext, editor: Editor) {
	const mounted = new WeakMap<HTMLElement, Mounted>();

	const render = (el: HTMLElement, model: GjsComponent): (() => void) => {
		const current = mounted.get(el);
		if (current) unmount(current.instance);
		el.innerHTML = '';
		const editing = current?.editing ?? createTextEditing({ editor, model, type, host: el, rerender: () => render(el, model) });
		const context = yackContextMap(yackContext, editing);
		const instance = mount(BlockRenderer, { target: el, props: { block: blockOf(type, model.getAttributes()) }, context });
		const handler: () => void = current?.handler ?? (() => editing.handleChange() || render(el, model));
		mounted.set(el, { instance, handler, editing });
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
			current.editing.dispose();
			unmount(current.instance);
			this.model.off('change:attributes', current.handler);
			mounted.delete(this.el);
		}
	};
}

export interface EditorOptions {
	container: HTMLElement;
	blocksEl: HTMLElement;
	content: { blocks: PageBlock[]; grapesData: Record<string, unknown> | null };
	/** null: CMS page (free blocks only); otherwise the template type (+ its bound blocks). */
	templateType: TemplateType | null;
	/** Context of the canvas blocks (editor: true). */
	context: YackContext;
	/** Level and charte of the administrator (field types, after loadTraitTypes()). */
	me: BuilderMe;
}

export function initEditor({ container, blocksEl, content, templateType, context, me }: EditorOptions): Editor {
	const allowed = allowedBlocks(templateType);
	const mandatory = allowed.filter((entry) => entry.mandatory).map((entry) => entry.type);
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
		// No GrapesJS trait panel: BlockPanel renders the traits of the selection in its tabs.
		traitManager: {},
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

	registerTraits(editor, me);

	// Every type is registered (stored data stays readable); the palette offers the allowed ones.
	for (const entry of REGISTRY) {
		editor.DomComponents.addType(entry.type, {
			model: {
				defaults: {
					tagName: 'div',
					name: entry.label,
					// Props live in attributes (read by pickProps on save) — not as model props.
					attributes: { ...entry.defaults },
					traits: [...entry.traits, ...STYLE_TRAITS].map((trait) => ({ placeholder: '', ...trait, changeProp: false })), // no GrapesJS default placeholder (« eg. Text here »)
					droppable: false,
					editable: false,
					stylable: false,
					resizable: false,
					highlightable: true,
					layerable: false,
					draggable: (_source: GjsComponent, target: GjsComponent) => target.get('type') === 'wrapper',
					...(entry.mandatory ? { removable: false, copyable: false, yackMandatory: true } : {})
				}
			},
			view: svelteView(entry.type, context, editor)
		});

		// Mandatory blocks are always on the page: nothing to add.
		if (!allowed.includes(entry) || entry.mandatory) continue;
		editor.Blocks.add(entry.type, {
			label: `<strong>${entry.label}</strong><small>${entry.description}</small>`,
			media: entry.icon,
			category: entry.scope === null ? 'Blocs Yack' : entry.scope === 'product' ? 'Blocs produit' : 'Blocs catégorie',
			content: { type: entry.type },
			// Click = append at the end of the page (in addition to drag & drop).
			onClick: (_block, ed) => {
				const [added] = ed.getWrapper()!.append({ type: entry.type });
				ed.select(added);
				added.getEl()?.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		});
	}

	const grapes = content.grapesData;
	if (grapes && Array.isArray(grapes.pages) && grapes.pages.length > 0) {
		editor.loadProjectData(grapes as ProjectData);
		takeStyleFromBlocks(editor, content.blocks);
	} else {
		editor
			.getWrapper()!
			.components(content.blocks.map((block) => ({ type: block.type, attributes: { ...block.props, ...styleAttributes(block.style) } })));
	}

	// After loading: the project data would otherwise restore a default wrapper.
	const wrapper = editor.getWrapper()!;
	const allowedTypes = new Set<unknown>(allowed.map((entry) => entry.type));
	wrapper.set({ stylable: false, droppable: (source: GjsComponent) => allowedTypes.has(source.get('type')) });
	for (const type of mandatory) {
		if (!wrapper.components().some((component: GjsComponent) => component.get('type') === type)) wrapper.append({ type });
	}

	editor.on('load', () => {
		const doc = editor.Canvas.getDocument();
		if (doc) {
			const style = doc.createElement('style');
			// Links are not followed in the canvas; a text editable in place (D8, data-yack-edit) takes the
			// pointer even inside a link, shows where it is, and keeps its spaces and line breaks while typed.
			style.textContent = [
				'body{padding-bottom:4rem;min-height:100vh}a{pointer-events:none}',
				'[data-yack-edit]{pointer-events:auto;cursor:text}',
				'[data-yack-edit]:hover,[data-yack-edit]:focus-visible{outline:1px dashed var(--yack-magenta,currentColor);outline-offset:3px}',
				'[data-yack-edit][contenteditable]{outline:2px solid var(--yack-magenta,currentColor);outline-offset:3px;white-space:pre-wrap;-webkit-user-select:text;user-select:text}'
			].join('');
			doc.head.appendChild(style);
		}
		const first = wrapper.components().at(0);
		if (first) editor.select(first);
	});

	return editor;
}

/**
 * The blocks are the source of truth (ADR 0002), the project data a comfort: after loading it, the
 * style of each block is the one of the saved blocks (a style changed by Sylius, a free value brought
 * back to its key, is not undone by an older project). Only when both list the same block types in
 * the same order; otherwise the project stays as it is.
 */
function takeStyleFromBlocks(editor: Editor, blocks: PageBlock[]): void {
	const components = editor
		.getWrapper()!
		.components()
		.filter((component: GjsComponent) => isBlockType(component.get('type')));
	if (components.length !== blocks.length || components.some((component: GjsComponent, i: number) => component.get('type') !== blocks[i]?.type)) return;
	components.forEach((component: GjsComponent, i: number) => {
		const changes = styleAttributeChanges(component.getAttributes(), blocks[i]?.style);
		if (changes) component.addAttributes(changes, { avoidStore: true });
	});
}

/** Page content to save: the ordered blocks (rendering source) + the GrapesJS project. */
export function collectPage(editor: Editor): { blocks: PageBlock[]; grapesData: Record<string, unknown> } {
	const blocks: PageBlock[] = [];
	editor
		.getWrapper()!
		.components()
		.forEach((component: GjsComponent) => {
			const type: unknown = component.get('type');
			if (isBlockType(type)) blocks.push(blockOf(type, component.getAttributes()));
		});

	return { blocks, grapesData: editor.getProjectData() };
}
