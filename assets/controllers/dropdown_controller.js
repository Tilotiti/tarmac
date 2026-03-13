import { Controller } from '@hotwired/stimulus';

/**
 * Dropdown controller - ensures dropdown works when Bootstrap/Tabler init fails.
 * Positions menu with fixed placement to stay within viewport.
 */
export default class extends Controller {
    static targets = ['menu', 'toggle'];

    connect() {
        this.toggleTarget.addEventListener('click', this.toggle.bind(this));
        document.addEventListener('click', this.outsideClick.bind(this));
    }

    disconnect() {
        document.removeEventListener('click', this.outsideClick.bind(this));
    }

    toggle(event) {
        event.preventDefault();
        event.stopPropagation();
        const isOpening = !this.menuTarget.classList.contains('show');
        if (isOpening) {
            this.positionMenu();
        } else {
            this.clearPosition();
        }
        this.menuTarget.classList.toggle('show');
        this.element.classList.toggle('show');
        this.toggleTarget.setAttribute('aria-expanded', isOpening);
    }

    positionMenu() {
        const rect = this.toggleTarget.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const spaceBelow = viewportHeight - rect.bottom;
        const spaceAbove = rect.top;
        const estimatedMenuHeight = 180;

        this.menuTarget.style.position = 'fixed';
        this.menuTarget.style.minWidth = `${Math.max(rect.width, 160)}px`;

        const menuWidth = this.menuTarget.offsetWidth || 200;
        const left = Math.max(8, Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8));
        this.menuTarget.style.left = `${left}px`;
        this.menuTarget.style.right = 'auto';

        if (spaceBelow >= estimatedMenuHeight || spaceBelow >= spaceAbove) {
            this.menuTarget.style.top = `${rect.bottom + 4}px`;
            this.menuTarget.style.bottom = 'auto';
        } else {
            this.menuTarget.style.bottom = `${viewportHeight - rect.top + 4}px`;
            this.menuTarget.style.top = 'auto';
        }
    }

    clearPosition() {
        this.menuTarget.style.position = '';
        this.menuTarget.style.top = '';
        this.menuTarget.style.bottom = '';
        this.menuTarget.style.left = '';
        this.menuTarget.style.right = '';
        this.menuTarget.style.minWidth = '';
    }

    outsideClick(event) {
        if (!this.element.contains(event.target)) {
            this.menuTarget.classList.remove('show');
            this.element.classList.remove('show');
            this.toggleTarget.setAttribute('aria-expanded', 'false');
            this.clearPosition();
        }
    }
}
