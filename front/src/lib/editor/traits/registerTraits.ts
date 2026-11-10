// Field types of the block panel (GrapesJS trait types, D9). A rich field is a Svelte component mounted
// in the trait's input: it receives the trait, its value and `set()`, writes through
// `component.addAttributes()` (canvas re-render, undo with Ctrl+Z, project data), and its exported
// `update()` receives the value again when it changes elsewhere (undo, another field).
//
// Adding a field type: replace the comment of its slot at the end of this file by one line,
//     defineTraitType('yack-xxx', () => import('./YackXxx.svelte'));
// (the blank lines between slots let parallel branches merge without conflict). The component's
// props are `TraitFieldProps`; it exports `update(value)`. Then use `type: 'yack-xxx'` in a trait of
// `#lib/blocks/registry.ts` (any extra key of the definition is read with `traitSetting(trait, 'key')`).
import type { Component as GjsComponent, Editor, Trait } from 'grapesjs';
import { mount, unmount, type Component } from 'svelte';
import { normalizeAnchor, styleAttribute, type BuilderMe } from '#lib/editor/panels/blockStyle.js';
import { pageComponents } from '#lib/editor/layouts.js';

/** Props of a field component. */
export interface TraitFieldProps {
	/**
	 * GrapesJS trait: `getName()`, `getLabel()`, and every key of its definition in the registry
	 * (`traitSetting(trait, 'styleProperty')`…).
	 */
	trait: Trait;
	/** Current value ('' = not set). */
	value: string;
	/** Writes the value into the block (one undo step). */
	set: (value: string) => void;
	/** Block being edited. */
	component: GjsComponent;
	editor: Editor;
	/** Level and charte of the logged-in administrator (GET /builder/me). */
	me: BuilderMe;
	/** id of the field's label, for `aria-labelledby`. */
	labelId: string;
}

/** Key of the trait definition that GrapesJS does not know (`styleProperty`, `charte`, `tab`…). */
export function traitSetting<T = unknown>(trait: Trait, key: string): T | undefined {
	return (trait.attributes as Record<string, unknown>)[key] as T | undefined;
}

/** Exports of a field component. */
export interface TraitField {
	update(value: string): void;
}

type FieldComponent = Component<TraitFieldProps, TraitField>;
type FieldModule = { default: FieldComponent };

const loaders = new Map<string, () => Promise<FieldModule>>();
const loaded = new Map<string, FieldComponent>();
const fields = new Map<HTMLElement, TraitField>();
let fieldCount = 0;

/** Declares a field type (slots at the end of this file). */
export function defineTraitType(type: string, load: () => Promise<FieldModule>): void {
	loaders.set(type, load);
}

/** Loads the field components; before `registerTraits`. */
export async function loadTraitTypes(): Promise<void> {
	await Promise.all(
		[...loaders].map(async ([type, load]) => {
			if (!loaded.has(type)) loaded.set(type, (await load()).default);
		})
	);
}

/** Trait type of a Svelte field: no GrapesJS label (the field shows its own) and no captured `change`. */
function svelteTraitType(editor: Editor, me: BuilderMe, field: FieldComponent) {
	return {
		noLabel: true,
		eventCapture: [],
		createInput({ trait, component }: { trait: Trait; component: GjsComponent }): HTMLElement {
			const host = document.createElement('div');
			host.className = 'yb-trait-field';
			const name = trait.getName();
			const instance = mount(field, {
				target: host,
				props: {
					trait,
					value: String(trait.getValue() ?? ''),
					set: (value: string) => component.addAttributes({ [name]: value }),
					component,
					editor,
					me,
					labelId: `yb-trait-${++fieldCount}`
				}
			});
			fields.set(host, instance);
			return host;
		},
		onUpdate({ elInput, trait }: { elInput: HTMLElement; trait: Trait }) {
			fields.get(elInput)?.update(String(trait.getValue() ?? ''));
		}
	};
}

/**
 * Anchor of the block (Avancé tab): lower case, ANCHOR_PATTERN, not an id of the shop layout, not
 * the anchor of another block of the page; a refused value stays in the input with the reason.
 */
function anchorTraitType(editor: Editor) {
	const messages = new WeakMap<HTMLElement, HTMLElement>();
	return {
		createInput({ trait }: { trait: Trait }): HTMLElement {
			const wrap = document.createElement('div');
			const input = document.createElement('input');
			input.type = 'text';
			input.maxLength = 40;
			input.placeholder = trait.get('placeholder') ?? '';
			// The GrapesJS label is a div, not a <label>: the input carries its own name.
			input.setAttribute('aria-label', trait.getLabel());
			input.setAttribute('aria-describedby', `${trait.cid}-anchor-help`);
			const help = document.createElement('small');
			help.id = `${trait.cid}-anchor-help`;
			help.className = 'yb-trait-help';
			help.textContent = 'L’adresse de la page suivie de #ancre mène à ce bloc.';
			const message = document.createElement('small');
			message.className = 'yb-trait-error';
			message.setAttribute('role', 'alert');
			wrap.append(input, help, message);
			messages.set(wrap, message);
			return wrap;
		},
		onEvent({ elInput, component, trait }: { elInput: HTMLElement; component: GjsComponent; trait: Trait }) {
			const input = elInput.querySelector('input');
			const message = messages.get(elInput);
			if (!input || !message) return;
			const name = styleAttribute('ancre');
			const taken: string[] = [];
			const wrapper = editor.getWrapper();
			for (const other of wrapper ? pageComponents(wrapper) : []) {
				const anchor = String(other.getAttributes()[name] ?? '');
				if (other !== component && anchor !== '') taken.push(anchor);
			}
			const result = normalizeAnchor(input.value, taken);
			if (!result.ok) {
				message.textContent = result.message;
				input.setAttribute('aria-invalid', 'true');
				return;
			}
			message.textContent = '';
			input.removeAttribute('aria-invalid');
			input.value = result.value;
			component.addAttributes({ [trait.getName()]: result.value });
		},
		onUpdate({ elInput, component, trait }: { elInput: HTMLElement; component: GjsComponent; trait: Trait }) {
			const input = elInput.querySelector('input');
			if (input) input.value = String(component.getAttributes()[trait.getName()] ?? '');
			const message = messages.get(elInput);
			if (message) message.textContent = '';
			input?.removeAttribute('aria-invalid');
		}
	};
}

/** Registers the field types on the editor (after `loadTraitTypes`). */
export function registerTraits(editor: Editor, me: BuilderMe): void {
	editor.Traits.addType('textarea', {
		createInput({ trait }) {
			const input = document.createElement('textarea');
			input.rows = 5;
			input.placeholder = trait.get('placeholder') ?? '';
			return input;
		},
		onEvent({ elInput, component, trait }) {
			component.addAttributes({ [trait.getName()]: (elInput as unknown as HTMLTextAreaElement).value });
		},
		onUpdate({ elInput, component, trait }) {
			(elInput as unknown as HTMLTextAreaElement).value = component.getAttributes()[trait.getName()] ?? '';
		}
	});
	editor.Traits.addType('yack-anchor', anchorTraitType(editor));
	for (const [type, field] of loaded) editor.Traits.addType(type, svelteTraitType(editor, me, field));
	keepLabels(editor);
}

/**
 * The label of a field as the registry writes it. GrapesJS's TraitView.getLabel() turns every hyphen into a space
 * (« Sous titre », « Espace au dessus »): every field type, GrapesJS's own and ours, shows the label unchanged.
 */
function keepLabels(editor: Editor): void {
	type LabelView = { em: { t(key: string): string | undefined }; model: Trait };
	for (const View of Object.values(editor.Traits.getTypes() as Record<string, { prototype: object }>)) {
		Object.defineProperty(View.prototype, 'getLabel', {
			configurable: true,
			writable: true,
			value(this: LabelView): string {
				return this.em.t(`traitManager.traits.labels.${this.model.getName()}`) || traitLabel(this.model);
			}
		});
	}
}

/** Label of a field: the `label` of its definition (else its name), first letter in capitals, hyphens kept. */
export function traitLabel(trait: Pick<Trait, 'getName' | 'get'>): string {
	const label = String(trait.get('label') || trait.getName());
	return label.charAt(0).toUpperCase() + label.slice(1);
}

/** Unmounts the fields rendered in `container` (the panel renders the next block's fields). */
export function releaseTraitFields(container: HTMLElement): void {
	for (const [host, instance] of fields) {
		if (!container.contains(host)) continue;
		unmount(instance);
		fields.delete(host);
	}
}

// ── Field types (one slot per type; keep a blank line between slots) ──

defineTraitType('yack-color', () => import('./YackColor.svelte'));

defineTraitType('yack-choice', () => import('./YackChoice.svelte'));

defineTraitType('yack-link', () => import('./YackLink.svelte'));

defineTraitType('yack-list', () => import('./YackList.svelte'));

defineTraitType('yack-image', () => import('./YackImage.svelte'));

defineTraitType('yack-icons', () => import('./YackIcons.svelte'));

defineTraitType('yack-spacing', () => import('./YackSpacing.svelte'));
