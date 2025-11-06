import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input'];

    connect() {
        // If target is not found, try to find the input element
        if (!this.hasInputTarget) {
            this.inputElement = this.element.querySelector('input[type="file"]');
            if (!this.inputElement) {
                console.warn('file-input-trigger: input[type="file"] not found');
            }
        }
    }

    trigger() {
        const input = this.hasInputTarget ? this.inputTarget : this.inputElement;
        if (input) {
            input.click();
        }
    }
}

