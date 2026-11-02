import { Controller } from '@hotwired/stimulus';

/* Links the numbered hotspots of an exploded view with the parts table rows. */
export default class extends Controller {
    static targets = ['hotspot', 'row'];

    highlight(event) {
        this.toggle(event.currentTarget.dataset.marker, true);
    }

    clear(event) {
        this.toggle(event.currentTarget.dataset.marker, false);
    }

    focus(event) {
        const row = this.rowTargets.find((element) => element.dataset.marker === event.currentTarget.dataset.marker);
        if (row) {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.querySelector('input[name="quantity"]')?.focus({ preventScroll: true });
        }
    }

    toggle(marker, active) {
        [...this.hotspotTargets, ...this.rowTargets]
            .filter((element) => element.dataset.marker === marker)
            .forEach((element) => element.classList.toggle('is-active', active));
    }
}
