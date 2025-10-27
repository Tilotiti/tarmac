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
        this.element.querySelectorAll('[data-confirm]').forEach(element => {
            element.addEventListener('click', this.confirm.bind(this));
        });
    }

    confirm(event) {
        event.preventDefault();
        const element = event.target.closest('[data-confirm]') || event.target;
        const confirmLabel = element.dataset.confirm;

        Swal.fire({
            title: this.titleValue || 'areYouSure',
            text: confirmLabel,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: this.yesValue || 'yes',
            cancelButtonText: this.cancelValue || 'cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                if (element.tagName === 'BUTTON' && element.form) {
                    element.form.submit();
                } else if (element.tagName === 'A') {
                    window.location.href = element.href;
                }
            }
        });
    }
}

