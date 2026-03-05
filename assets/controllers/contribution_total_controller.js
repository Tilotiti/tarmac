import { Controller } from '@hotwired/stimulus';

/**
 * Recalcule le temps total du formulaire de clôture à partir
 * des champs hours/minutes de chaque contribution.
 */
export default class extends Controller {
    static targets = ['container', 'total'];

    connect() {
        this.boundRefresh = this.refresh.bind(this);

        if (this.hasContainerTarget) {
            this.containerTarget.addEventListener('input', this.boundRefresh);
            this.containerTarget.addEventListener('change', this.boundRefresh);
        }

        this.observeContainer();
        this.refresh();
    }

    disconnect() {
        if (this.observer) this.observer.disconnect();

        if (this.hasContainerTarget && this.boundRefresh) {
            this.containerTarget.removeEventListener('input', this.boundRefresh);
            this.containerTarget.removeEventListener('change', this.boundRefresh);
        }
    }

    observeContainer() {
        if (!this.hasContainerTarget) return;

        this.observer = new MutationObserver(() => {
            requestAnimationFrame(() => this.refresh());
        });

        this.observer.observe(this.containerTarget, { childList: true, subtree: true });
    }

    refresh() {
        if (!this.hasContainerTarget || !this.hasTotalTarget) return;

        let totalMinutes = 0;
        const items = this.containerTarget.querySelectorAll('[data-collection-type-target="item"]');

        items.forEach((item) => {
            const hoursInput = item.querySelector('input[name*="[timeSpent][hours]"]');
            const minutesInput = item.querySelector('input[name*="[timeSpent][minutes]"]');

            const hours = Number.parseInt(hoursInput?.value ?? '0', 10) || 0;
            const minutes = Number.parseInt(minutesInput?.value ?? '0', 10) || 0;

            totalMinutes += Math.max(0, hours) * 60 + Math.max(0, Math.min(59, minutes));
        });

        this.totalTarget.textContent = this.formatMinutes(totalMinutes);
    }

    formatMinutes(totalMinutes) {
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;

        if (hours === 0) {
            return `${minutes} minute${minutes > 1 ? 's' : ''}`;
        }

        if (minutes === 0) {
            return `${hours} heure${hours > 1 ? 's' : ''}`;
        }

        return `${hours} heure${hours > 1 ? 's' : ''} ${minutes} minute${minutes > 1 ? 's' : ''}`;
    }
}
