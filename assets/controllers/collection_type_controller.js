import { Controller } from '@hotwired/stimulus';

// Use a WeakMap to store index counters per controller instance
const indexCounters = new WeakMap();

export default class extends Controller {
    static targets = ['container', 'item'];
    static values = {
        prototypeName: String,
        displayIndexName: String,
        index: { type: Number, default: 0 }
    };

    connect() {
        // Initialize index based on existing items in THIS specific collection
        // Count only the direct children items of this controller's container
        const existingItems = this.containerTarget.querySelectorAll(':scope > [data-collection-type-target="item"]');

        // ALWAYS reset the counter when connecting - don't trust any existing value
        // Each controller should start fresh based on its actual DOM state
        const startIndex = existingItems.length;
        indexCounters.set(this.element, startIndex);

        this.updatePlaceholderVisibility();
    }

    addItem(event) {
        event.preventDefault();

        // Get the prototype from the data attribute
        const prototype = this.element.dataset.prototype;
        if (!prototype) {
            console.error('No prototype found in data-prototype attribute');
            return;
        }

        // Get the index counter for THIS specific controller instance from the WeakMap
        const currentIndex = indexCounters.get(this.element) || 0;

        // Replace __name__ or the prototype name placeholder with actual index
        const prototypeName = this.prototypeNameValue || '__name__';
        let newItem = prototype.replace(new RegExp(prototypeName, 'g'), currentIndex);

        // ALSO always replace __name__ in case the prototypeName is different
        // This ensures Symfony form field names get updated correctly
        if (prototypeName !== '__name__') {
            newItem = newItem.replace(/__name__/g, currentIndex);
        }

        // Also replace __INDEX__ with the visual index (1-based)
        const displayIndexPlaceholder = this.displayIndexNameValue || '__INDEX__';
        const displayIndex = currentIndex + 1;
        newItem = newItem.replace(new RegExp(displayIndexPlaceholder, 'g'), displayIndex);

        // Handle nested collections: replace __PARENT_INDEX__ with parent task number
        const parentCard = this.element.closest('.card[data-collection-type-target="item"]');

        if (parentCard) {
            // Find the parent container of all tasks
            const tasksContainer = parentCard.parentElement;

            // Get all task cards in the container
            const allTaskCards = tasksContainer.querySelectorAll('.card[data-collection-type-target="item"]');

            // Find the index of this parent card
            const parentIndex = Array.from(allTaskCards).indexOf(parentCard) + 1;
            newItem = newItem.replace(/__PARENT_INDEX__/g, parentIndex);
        } else {
            // No parent; this is a top-level collection (e.g., tasks)
        }

        // Create a temporary container to parse the HTML
        const temp = document.createElement('div');
        temp.innerHTML = newItem;

        // Append the new item to the container
        const newElement = temp.firstElementChild;
        this.containerTarget.appendChild(newElement);

        // Increment the index for next item for THIS controller instance in the WeakMap
        indexCounters.set(this.element, currentIndex + 1);

        // Update placeholder visibility
        this.updatePlaceholderVisibility();
    }

    removeItem(event) {
        event.preventDefault();
        const item = event.target.closest('[data-collection-type-target="item"]');
        if (item) {
            item.remove();
            this.updatePlaceholderVisibility();
        }
    }

    updatePlaceholderVisibility() {
        // Find the empty placeholder in this container
        const placeholder = this.containerTarget.querySelector('.empty');
        if (placeholder) {
            // Count only direct children items of this controller's container
            const existingItems = this.containerTarget.querySelectorAll(':scope > [data-collection-type-target="item"]');
            // Hide placeholder if there are items, show if empty
            if (existingItems.length > 0) {
                placeholder.style.display = 'none';
            } else {
                placeholder.style.display = 'block';
            }
        }
    }
}

