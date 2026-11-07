// "−" / "+" buttons of a .yack-qty field (product card, product page): JavaScript only, the field
// stays typed in without it. The input event keeps the remote form field in sync.
export function stepQuantity(event: MouseEvent, direction: 1 | -1): void {
	const input = (event.currentTarget as HTMLElement).parentElement?.querySelector('input');
	if (!input) return;
	if (direction > 0) input.stepUp();
	else input.stepDown();
	input.dispatchEvent(new Event('input', { bubbles: true }));
}
