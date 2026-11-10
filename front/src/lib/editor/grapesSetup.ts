// GrapesJS set-up adapted from sveltekit-builder, locked to the Yack charte:
//  - only Yack blocks (no basic / raw HTML / code blocks, no import): free blocks for a page, plus
//    the bound blocks of its type for a template — whose mandatory block is always present, cannot
//    be removed nor duplicated (model prop `yackMandatory`, shown as a padlock by the tree);
//  - no style manager, selector manager or layer panel: editors edit props and the named style of the
//    block (traits, rendered by panels/BlockPanel.svelte in the tabs Contenu, Style, Avancé); the style
//    lives in the attributes `yb-<property>` (panels/blockStyle.ts), next to the props;
//  - blocks are atomic (not droppable, no GrapesJS rich text editor) and sit at page level, in a layout
//    container (section, colonnes: layouts.ts) or — product blocks — in their column of a product zone,
//    laid out as on the shop (zones.ts); their texts are typed in place through the block's own text
//    editing (D8, inline/textEditing.ts);
//  - the canvas renders the shop: same CSS (Sylius shop CSS, same origin behind nginx), same
//    components, same product zones, at the device width (desktop: 1440 px, zoomed out to fit) and,
//    for a template, inside the shop header / breadcrumb / footer (`chrome`).
import grapesjs, { type Component as GjsComponent, type Editor, type ProjectData } from 'grapesjs';
import { mount, unmount, type Snippet } from 'svelte';
import BlockRenderer from '#lib/blocks/BlockRenderer.svelte';
import { yackContextMap, type YackContext } from '#lib/blocks/context.js';
import { allowedBlocks, blockDefinition, editorStyleTraits, paletteBlocks, REGISTRY, pickProps } from '#lib/blocks/registry.js';
import { flattenNodes } from '#lib/blocks/containers.js';
import type { BlockType, PageBlock, TemplateType } from '#lib/blocks/types.js';
import { pickStyle, styleAttributeChanges, type BuilderMe } from '#lib/editor/panels/blockStyle.js';
import { registerTraits } from '#lib/editor/traits/registerTraits.js';
import { createTextEditing, type TextEditingController } from '#lib/editor/inline/textEditing.js';
import SnippetHost from './SnippetHost.svelte';
import { addLayoutTypes, blockComponent, blockParents, componentBlock, pageComponents } from './layouts.js';
import { addZoneTypes, collectBlocks, COLUMN_ATTRIBUTE, installZones, normalizeZones, ZONE_TYPE, zonedComponents } from './zones.js';

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
 * `context`: viewer of the canvas (« Voir comme »), editor loaders and, for a template, the sample product / category
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
	content: { blocks: PageBlock[]; grapesData: Record<string, unknown> | null };
	/** null: CMS page (free blocks only); otherwise the template type (+ its bound blocks). */
	templateType: TemplateType | null;
	/** Context of the canvas blocks (editor: true). */
	context: YackContext;
	/** Level and charte of the administrator (field types, after loadTraitTypes()). */
	me: BuilderMe;
	/** Shop chrome around the canvas content (template: header + breadcrumb, footer), inert. */
	chrome?: CanvasChrome | null;
}

export interface CanvasChrome {
	top: Snippet;
	bottom: Snippet;
}

/**
 * Shows the whole device width: desktop = 1440 px (the shop's large screen), zoomed out when the
 * canvas is narrower, the frame as tall as the canvas. The frames area scales around its center:
 * the offset puts the frame back at the top left corner.
 */
function fitCanvas(editor: Editor): void {
	const canvas = editor.Canvas.getElement();
	const frame = editor.Canvas.getFrames()[0];
	const deviceWidth = parseFloat(String(editor.Devices.getSelected()?.get('width') ?? ''));
	if (!canvas || !frame || !deviceWidth || !canvas.clientWidth || !canvas.clientHeight) return;
	const { clientWidth: width, clientHeight: height } = canvas;
	const zoom = Math.min(1, width / deviceWidth);
	editor.Canvas.setZoom(zoom * 100);
	editor.Canvas.setCoords(-(width / 2) * (1 - zoom), -(height / 2) * (1 - zoom));
	frame.set({ height: `${height / zoom}px` });
}

const PAGE_CANVAS_CSS = 'body{padding-bottom:4rem;min-height:100vh}';
// Template: the body is the shell of the root layout (+layout.svelte: footer at the bottom, room for
// the mobile tab bar); the page content (wrapper) between the chrome hosts.
const SHELL_CLASSES = ['yack-shell', 'd-flex', 'flex-column', 'min-vh-100'];
const TEMPLATE_CANVAS_CSS = 'body>[data-gjs-type=wrapper]{min-height:0!important}.yb-canvas-bottom{margin-top:auto}';
// Links are not followed in the canvas; a text editable in place (D8, data-yack-edit) takes the
// pointer even inside a link, shows where it is, and keeps its spaces and line breaks while typed.
// Empty column (product zone, colonnes) or empty section: drop area.
const CANVAS_CSS = [
	'a{pointer-events:none}',
	'[data-yack-edit]{pointer-events:auto;cursor:text}',
	'[data-yack-edit]:hover,[data-yack-edit]:focus-visible{outline:1px dashed var(--yack-magenta,currentColor);outline-offset:3px}',
	'[data-yack-edit][contenteditable]{outline:2px solid var(--yack-magenta,currentColor);outline-offset:3px;white-space:pre-wrap;-webkit-user-select:text;user-select:text}',
	`[${COLUMN_ATTRIBUTE}]:empty{min-height:6rem;outline:2px dashed var(--yack-gray,currentColor);outline-offset:-4px}`,
	// The selected block: magenta outline of the charte (GrapesJS draws it in blue, hard-coded in its canvas styles).
	'body .gjs-selected{outline:2px solid var(--yack-magenta,currentColor)!important;outline-offset:-2px}'
].join('');

export function initEditor({ container, content, templateType, context, me, chrome = null }: EditorOptions): Editor {
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
		// No GrapesJS block panel: the shell renders the palette (search, rubriques, thumbnails) from the
		// blocks added below and drags or clicks them through the Blocks API (#lib/editor/palette.ts).
		blockManager: {},
		// No GrapesJS trait panel: BlockPanel renders the traits of the selection in its tabs.
		traitManager: {},
		styleManager: { sectors: [] },
		layerManager: { appendTo: document.createElement('div') },
		selectorManager: { componentFirst: true },
		deviceManager: {
			devices: [
				{ id: 'desktop', name: 'Ordinateur', width: '1440px' },
				{ id: 'tablet', name: 'Tablette', width: '820px', widthMedia: '992px' },
				{ id: 'mobile', name: 'Mobile', width: '390px', widthMedia: '576px' }
			]
		},
		richTextEditor: { actions: [] },
		assetManager: { upload: false }
	});

	registerTraits(editor, me);

	addZoneTypes(editor);
	const refusedByColumn = addLayoutTypes(editor);
	// Every type is registered (stored data stays readable); the palette offers the allowed ones.
	for (const entry of REGISTRY.filter((candidate) => !candidate.container)) {
		const parents = new Set<unknown>(blockParents(entry.type));
		editor.DomComponents.addType(entry.type, {
			model: {
				defaults: {
					tagName: 'div',
					name: entry.label,
					// Props live in attributes (read by pickProps on save) — not as model props.
					attributes: { ...entry.defaults },
					traits: [...entry.traits, ...editorStyleTraits(entry.type)].map((trait) => ({ placeholder: '', ...trait, changeProp: false })), // no GrapesJS default placeholder (« eg. Text here »)
					droppable: false,
					editable: false,
					stylable: false,
					resizable: false,
					highlightable: true,
					layerable: false,
					draggable: (_source: GjsComponent, target: GjsComponent) => parents.has(target.get('type')),
					...(entry.mandatory ? { removable: false, copyable: false, yackMandatory: true } : {})
				}
			},
			view: svelteView(entry.type, context, editor)
		});
	}
	// Blocks of the palette, in its order (mandatory blocks are always on the page: nothing to add).
	for (const entry of paletteBlocks(templateType)) {
		editor.Blocks.add(entry.type, {
			label: entry.label,
			category: entry.category,
			content: { type: entry.type },
			// Click = append at the end of the page (in addition to drag & drop), in its zone; a container empty.
			onClick: (_block, ed) => {
				const [added] = ed.getWrapper()!.append({ type: entry.type });
				normalizeZones(ed.getWrapper()!);
				ed.select(added);
				added.getEl()?.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		});
	}

	// A template is built from its blocks (the zones are derived); a page restores its GrapesJS project.
	const grapes = content.grapesData;
	const toComponent = (block: PageBlock) => blockComponent(block);
	if (templateType !== null) {
		editor.getWrapper()!.components(zonedComponents(content.blocks, toComponent));
	} else if (grapes && Array.isArray(grapes.pages) && grapes.pages.length > 0) {
		editor.loadProjectData(grapes as ProjectData);
		takeStyleFromBlocks(editor, content.blocks);
	} else {
		editor.getWrapper()!.components(content.blocks.map(toComponent));
	}

	// After loading: the project data would otherwise restore a default wrapper.
	const wrapper = editor.getWrapper()!;
	const allowedTypes = new Set<unknown>([...allowed.map((entry) => entry.type), ZONE_TYPE]);
	wrapper.set({ stylable: false, droppable: (source: GjsComponent) => allowedTypes.has(source.get('type')) && !refusedByColumn(source) });
	for (const type of mandatory) {
		if (wrapper.findType(type).length === 0) wrapper.append({ type });
	}
	normalizeZones(wrapper);
	const uninstallZones = installZones(editor);

	const chromeMounts: Record<string, unknown>[] = [];
	const resize = new ResizeObserver(() => fitCanvas(editor));
	editor.on('change:device', () => requestAnimationFrame(() => fitCanvas(editor)));
	editor.on('load', () => {
		const doc = editor.Canvas.getDocument();
		if (doc) {
			const style = doc.createElement('style');
			style.textContent = CANVAS_CSS + (chrome ? TEMPLATE_CANVAS_CSS : PAGE_CANVAS_CSS);
			doc.head.appendChild(style);
		}
		const wrapperEl = wrapper.getEl();
		if (doc && wrapperEl && chrome) {
			doc.body.classList.add(...SHELL_CLASSES);
			for (const [where, snippet] of [['top', chrome.top], ['bottom', chrome.bottom]] as const) {
				const host = doc.createElement('div');
				host.className = `yb-canvas-${where}`;
				host.inert = true;
				if (where === 'top') wrapperEl.before(host);
				else wrapperEl.after(host);
				chromeMounts.push(mount(SnippetHost, { target: host, props: { snippet } }));
			}
		}
		const canvas = editor.Canvas.getElement();
		if (canvas) resize.observe(canvas);
		fitCanvas(editor);
		const first = wrapper.components().at(0);
		const firstBlock = first?.get('type') === ZONE_TYPE ? first.components().at(0)?.components().at(0) : first;
		if (firstBlock) editor.select(firstBlock);
	});
	editor.on('destroy', () => {
		resize.disconnect();
		uninstallZones();
		for (const instance of chromeMounts) unmount(instance);
	});

	return editor;
}

/**
 * The blocks are the source of truth (ADR 0002), the project data a comfort: after loading it, the
 * style of each block is the one of the saved blocks (a style changed by Sylius, a free value brought
 * back to its key, is not undone by an older project). Only when both list the same block types in
 * the same order (containers and their content included, document order); otherwise the project stays
 * as it is.
 */
function takeStyleFromBlocks(editor: Editor, blocks: PageBlock[]): void {
	const components = pageComponents(editor.getWrapper()!).filter((component: GjsComponent) => isBlockType(component.get('type')));
	const nodes = flattenNodes(blocks);
	if (components.length !== nodes.length || components.some((component: GjsComponent, i: number) => component.get('type') !== nodes[i]?.type)) return;
	components.forEach((component: GjsComponent, i: number) => {
		const changes = styleAttributeChanges(component.getAttributes(), nodes[i]?.style);
		if (changes) component.addAttributes(changes, { avoidStore: true });
	});
}

/** Page content to save: the ordered blocks (rendering source, zones flattened, containers with their content) + the GrapesJS project. */
export function collectPage(editor: Editor): { blocks: PageBlock[]; grapesData: Record<string, unknown> } {
	const blocks = collectBlocks(editor.getWrapper()!, componentBlock);

	return { blocks, grapesData: editor.getProjectData() };
}
