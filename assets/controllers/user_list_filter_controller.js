import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['search', 'item', 'empty', 'count'];
    static values = {
        type: String, // 'doneBy' or 'contributors'
    }

    connect() {
        this.updateCount();

        // If this is the contributors list, listen to doneBy changes
        if (this.typeValue === 'contributors') {
            this.syncWithDoneBy();
        }
    }

    filter() {
        const searchValue = this.searchTarget.value.toLowerCase().trim();
        let visibleCount = 0;

        this.itemTargets.forEach(item => {
            // The item is now the label itself, get its text content
            const labelText = item.textContent.toLowerCase().trim();
            const matches = labelText.includes(searchValue);
            
            if (matches) {
                item.classList.remove('d-none');
                visibleCount++;
            } else {
                item.classList.add('d-none');
            }
        });

        // Show/hide empty message
        if (this.hasEmptyTarget) {
            if (visibleCount === 0) {
                this.emptyTarget.classList.remove('d-none');
            } else {
                this.emptyTarget.classList.add('d-none');
            }
        }
    }

    updateCount() {
        if (!this.hasCountTarget) return;

        const checked = this.element.querySelectorAll('input[type="checkbox"]:checked').length;
        this.countTarget.textContent = checked;
    }

    // Sync contributors with doneBy selection
    syncWithDoneBy() {
        // Find the doneBy fieldset
        const doneByFieldset = document.querySelector('[data-user-list-filter-type-value="doneBy"]');
        if (!doneByFieldset) return;

        // Listen to changes in doneBy radio buttons
        const doneByRadios = doneByFieldset.querySelectorAll('input[type="radio"]');
        doneByRadios.forEach(radio => {
            radio.addEventListener('change', () => this.updateContributorsFromDoneBy());
        });

        // Initial sync
        this.updateContributorsFromDoneBy();
    }

    updateContributorsFromDoneBy() {
        // Find which doneBy radio is selected
        const doneByFieldset = document.querySelector('[data-user-list-filter-type-value="doneBy"]');
        if (!doneByFieldset) return;

        const selectedRadio = doneByFieldset.querySelector('input[type="radio"]:checked');
        if (!selectedRadio) return;

        const selectedValue = selectedRadio.value;

        // Find all contributor checkboxes and update them
        const contributorCheckboxes = this.element.querySelectorAll('input[type="checkbox"]');
        contributorCheckboxes.forEach(checkbox => {
            if (checkbox.value === selectedValue) {
                // Check and disable the checkbox for the selected doneBy member
                checkbox.checked = true;
                checkbox.disabled = true;
                checkbox.closest('.form-check').classList.add('opacity-75');
            } else {
                // Enable other checkboxes
                checkbox.disabled = false;
                checkbox.closest('.form-check').classList.remove('opacity-75');
            }
        });

        this.updateCount();
    }
}

