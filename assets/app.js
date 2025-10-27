import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import '@tabler/core';

console.log('Tarmac with Tabler.io - Ready! 🚀');

// Initialize Bootstrap features globally
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    // Auto-open modal if specified (for form validation errors)
    const modalToOpen = document.querySelector('[data-auto-open-modal]');
    if (modalToOpen) {
        const modalId = modalToOpen.getAttribute('data-auto-open-modal');
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
    }
});
