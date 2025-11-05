import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tag', 'checkbox'];

    connect() {
        this.updateTagStates();

        // Listen to checkbox changes (in case they're changed programmatically)
        this.checkboxTargets.forEach(checkbox => {
            checkbox.addEventListener('change', () => this.updateTagStates());
        });
    }

    toggle(event) {
        event.preventDefault();
        const tag = event.currentTarget;
        const value = tag.dataset.value;
        const checkbox = this.checkboxTargets.find(cb => cb.value === value);

        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            // Trigger change event so form knows about the change
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            this.updateTagStates();
        }
    }

    updateTagStates() {
        this.tagTargets.forEach(tag => {
            const value = tag.dataset.value;
            const checkbox = this.checkboxTargets.find(cb => cb.value === value);

            if (checkbox && checkbox.checked) {
                tag.classList.add('active');
                tag.classList.add('border');
                tag.classList.add('border-secondary');
                // Add check icon if not present
                if (!tag.querySelector('.ti-check')) {
                    const checkIcon = document.createElement('i');
                    checkIcon.className = 'ti ti-check ms-1';
                    tag.appendChild(checkIcon);
                }
            } else {
                tag.classList.remove('active');
                tag.classList.remove('border');
                tag.classList.remove('border-secondary');
                // Remove check icon if present
                const checkIcon = tag.querySelector('.ti-check');
                if (checkIcon) {
                    checkIcon.remove();
                }
            }
        });
    }
}

