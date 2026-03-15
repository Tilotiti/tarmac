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
        this.menuTarget.style.position = 'fixed';
        this.menuTarget.style.minWidth = `${Math.max(rect.width, 160)}px`;

        const menuWidth = this.menuTarget.offsetWidth || 200;
        const left = Math.max(8, Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8));
        this.menuTarget.style.left = `${left}px`;
        this.menuTarget.style.right = 'auto';

        // Toujours ouvrir vers le bas (navbar en haut = espace suffisant en dessous)
        const top = Math.max(8, rect.bottom + 4);
        this.menuTarget.style.top = `${top}px`;
        this.menuTarget.style.bottom = 'auto';

        // Limiter la hauteur pour rester dans le viewport
        const maxHeight = window.innerHeight - top - 16;
        this.menuTarget.style.maxHeight = `${maxHeight}px`;
        this.menuTarget.style.overflowY = 'auto';
    }

    clearPosition() {
        this.menuTarget.style.position = '';
        this.menuTarget.style.top = '';
        this.menuTarget.style.bottom = '';
        this.menuTarget.style.left = '';
        this.menuTarget.style.right = '';
        this.menuTarget.style.minWidth = '';
        this.menuTarget.style.maxHeight = '';
        this.menuTarget.style.overflowY = '';
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
