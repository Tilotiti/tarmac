import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['ownersContainer', 'counter'];

    connect() {
        this.updateOwnersVisibility();
        this.updateCounter();
    }

    toggleOwners() {
        this.updateOwnersVisibility();
    }

    updateOwnersVisibility() {
        const privateRadio = this.element.querySelector('input[type="radio"][value="private"]:checked');
        const isPrivate = privateRadio !== null;

        if (isPrivate) {
            this.ownersContainerTarget.classList.remove('d-none');
            const select = this.ownersContainerTarget.querySelector('select[name*="owners"]');
            if (select?.tomselect) {
                select.tomselect.refreshOptions(false);
            }
        } else {
            this.ownersContainerTarget.classList.add('d-none');
        }
    }

    updateCounter() {
        if (!this.hasCounterTarget || !this.hasOwnersContainerTarget) {
            return;
        }

        const select = this.ownersContainerTarget.querySelector('select[name*="owners"]');
        if (!select) {
            return;
        }

        let count = 0;
        if (select.tomselect) {
            const v = select.tomselect.getValue();
            count = Array.isArray(v) ? v.length : (v ? 1 : 0);
        } else {
            count = select.selectedOptions.length;
        }

        const text = count === 0
            ? 'Aucun utilisateur sélectionné'
            : count === 1
                ? '1 utilisateur sélectionné'
                : `${count} utilisateurs sélectionnés`;

        this.counterTarget.textContent = text;
    }
}
