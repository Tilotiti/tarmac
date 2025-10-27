import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        title: String,
        yes: String,
        cancel: String
    }

    connect() {
        // Store original actions for elements with data-confirm
        this.element.querySelectorAll('[data-confirm]').forEach(element => {
            // Store the original data-action if it exists
            if (element.hasAttribute('data-action')) {
                element.dataset.originalAction = element.dataset.action;
                // Replace the action to point to our intercept method
                element.dataset.action = element.dataset.action
                    .split(' ')
                    .map(action => {
                        // Only intercept click actions
                        if (action.includes('click->')) {
                            return `click->confirm#intercept`;
                        }
                        return action;
                    })
                    .join(' ');
            } else if (element.tagName === 'BUTTON' && element.form) {
                // For form submit buttons
                element.addEventListener('click', this.handleFormSubmit.bind(this));
            } else if (element.tagName === 'A') {
                // For links
                element.addEventListener('click', this.handleLink.bind(this));
            }
        });
    }

    intercept(event) {
        event.preventDefault();
        event.stopPropagation();
        
        const element = event.currentTarget;
        const confirmLabel = element.dataset.confirm;

        Swal.fire({
            title: this.titleValue || 'Êtes-vous sûr ?',
            text: confirmLabel,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: this.yesValue || 'Oui',
            cancelButtonText: this.cancelValue || 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                // Restore the original action and execute it
                if (element.dataset.originalAction) {
                    const originalAction = element.dataset.originalAction;
                    
                    // Parse the original action to find the controller and method
                    const clickActions = originalAction.split(' ').filter(a => a.includes('click->'));
                    
                    clickActions.forEach(action => {
                        const match = action.match(/click->(.+)#(.+)/);
                        if (match) {
                            const [, controllerName, methodName] = match;
                            
                            // Find the controller instance and call the method
                            const controllerElement = element.closest(`[data-controller*="${controllerName}"]`);
                            if (controllerElement) {
                                const controller = this.application.getControllerForElementAndIdentifier(
                                    controllerElement,
                                    controllerName
                                );
                                
                                if (controller && typeof controller[methodName] === 'function') {
                                    // Create a new event object
                                    const newEvent = new MouseEvent('click', {
                                        bubbles: true,
                                        cancelable: true,
                                        view: window
                                    });
                                    
                                    // Set the currentTarget to the button
                                    Object.defineProperty(newEvent, 'currentTarget', {
                                        writable: false,
                                        value: element
                                    });
                                    
                                    // Mark as confirmed to prevent re-interception
                                    element.dataset.skipConfirm = 'true';
                                    controller[methodName](newEvent);
                                    delete element.dataset.skipConfirm;
                                }
                            }
                        }
                    });
                }
            }
        });
    }

    handleFormSubmit(event) {
        const element = event.currentTarget;
        
        // Skip if already confirmed
        if (element.dataset.skipConfirm) {
            delete element.dataset.skipConfirm;
            return;
        }
        
        event.preventDefault();
        event.stopPropagation();
        
        const confirmLabel = element.dataset.confirm;

        Swal.fire({
            title: this.titleValue || 'Êtes-vous sûr ?',
            text: confirmLabel,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: this.yesValue || 'Oui',
            cancelButtonText: this.cancelValue || 'Annuler'
        }).then((result) => {
            if (result.isConfirmed && element.form) {
                element.form.submit();
            }
        });
    }

    handleLink(event) {
        const element = event.currentTarget;
        
        // Skip if already confirmed
        if (element.dataset.skipConfirm) {
            delete element.dataset.skipConfirm;
            return;
        }
        
        event.preventDefault();
        event.stopPropagation();
        
        const confirmLabel = element.dataset.confirm;

        Swal.fire({
            title: this.titleValue || 'Êtes-vous sûr ?',
            text: confirmLabel,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: this.yesValue || 'Oui',
            cancelButtonText: this.cancelValue || 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = element.href;
            }
        });
    }
}

