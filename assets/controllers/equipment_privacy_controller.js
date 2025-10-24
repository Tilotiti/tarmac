import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['ownerRadios', 'ownersContainer', 'filterInput', 'counter', 'noResults'];

    connect() {
        this.updateOwnersVisibility();
        this.updateCounter();
    }

    toggleOwners(event) {
        this.updateOwnersVisibility();
    }

    updateOwnersVisibility() {
        // Check if the private radio button is selected
        const privateRadio = this.element.querySelector('input[type="radio"][value="private"]:checked');
        const isPrivate = privateRadio !== null;

        if (isPrivate) {
            this.ownersContainerTarget.classList.remove('d-none');
        } else {
            this.ownersContainerTarget.classList.add('d-none');
        }
    }

    filterUsers() {
        const searchTerm = this.filterInputTarget.value.toLowerCase().trim();
        const userItems = this.getUserItems();
        let visibleCount = 0;

        userItems.forEach((item) => {
            const label = item.querySelector('label');
            if (!label) return;

            const userName = label.textContent.toLowerCase();

            if (searchTerm === '' || userName.includes(searchTerm)) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        // Show/hide no results message
        if (this.hasNoResultsTarget) {
            if (visibleCount === 0) {
                this.noResultsTarget.classList.remove('d-none');
            } else {
                this.noResultsTarget.classList.add('d-none');
            }
        }
    }

    updateCounter() {
        const userItems = this.getUserItems();
        const checkedCount = userItems.filter((item) => {
            const checkbox = item.querySelector('input[type="checkbox"]');
            return checkbox && checkbox.checked;
        }).length;

        const text = checkedCount === 0
            ? 'Aucun utilisateur sélectionné'
            : checkedCount === 1
                ? '1 utilisateur sélectionné'
                : `${checkedCount} utilisateurs sélectionnés`;

        this.counterTarget.textContent = text;
    }

    getUserItems() {
        // Get all checkbox containers within the owners container
        const container = this.ownersContainerTarget.querySelector('.owners-list-container');
        if (!container) return [];

        return Array.from(container.querySelectorAll('.form-check'));
    }
}

