import { Controller } from '@hotwired/stimulus';

/**
 * Filter offcanvas controller – fixes backdrop not disappearing on first click.
 * - On backdrop click: explicitly close offcanvas and remove backdrop immediately.
 * - On hidden: clean up any remaining backdrop (Bootstrap sometimes leaves it).
 */
export default class extends Controller {
    static values = {
        offcanvasId: { type: String, default: 'offCanvasFilters' },
    };

    connect() {
        this.offcanvasEl = document.getElementById(this.offcanvasIdValue);
        if (!this.offcanvasEl) return;

        this.boundOnBackdropClick = this.onBackdropClick.bind(this);
        this.boundOnHidden = this.onHidden.bind(this);

        document.addEventListener('click', this.boundOnBackdropClick, true);
        this.offcanvasEl.addEventListener('hidden.bs.offcanvas', this.boundOnHidden);
    }

    disconnect() {
        document.removeEventListener('click', this.boundOnBackdropClick, true);
        if (this.offcanvasEl) {
            this.offcanvasEl.removeEventListener('hidden.bs.offcanvas', this.boundOnHidden);
        }
    }

    onBackdropClick(event) {
        if (!event.target.classList.contains('offcanvas-backdrop')) return;

        const offcanvas = document.querySelector(`#${this.offcanvasIdValue}.show`);
        if (!offcanvas || !window.bootstrap?.Offcanvas) return;

        const instance = window.bootstrap.Offcanvas.getInstance(offcanvas);
        if (instance) {
            instance.hide();
        }
    }

    onHidden() {
        document.querySelectorAll('.offcanvas-backdrop').forEach((el) => el.remove());
    }
}
