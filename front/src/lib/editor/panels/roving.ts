// Keyboard of a group of buttons with one tab stop (swatches, segmented buttons): the arrows, Home and
// End move the focus, Enter or Space picks (the button's own click). Pure, tested by test/block-style.test.ts.

/** Index the focus goes to after `key`, or null when the key does not move it. */
export function rovingIndex(key: string, index: number, count: number): number | null {
	if (count <= 0) return null;
	switch (key) {
		case 'ArrowRight':
		case 'ArrowDown':
			return (index + 1) % count;
		case 'ArrowLeft':
		case 'ArrowUp':
			return (index - 1 + count) % count;
		case 'Home':
			return 0;
		case 'End':
			return count - 1;
		default:
			return null;
	}
}

/** Moves the focus among the `button`s of `group` (event.currentTarget) and returns the new index. */
export function rovingKeydown(event: KeyboardEvent, index: number): number | null {
	const group = event.currentTarget as HTMLElement;
	const buttons = [...group.querySelectorAll<HTMLButtonElement>('button[data-roving]')];
	const next = rovingIndex(event.key, index, buttons.length);
	if (next === null) return null;
	event.preventDefault();
	buttons[next]?.focus();
	return next;
}
