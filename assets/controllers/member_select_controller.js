import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

/**
 * Tom Select sur les EntityType membre/utilisateur : recherche locale sur le libellé.
 */
export default class extends Controller {
    static values = {
        multiple: { type: Boolean, default: false },
        placeholder: { type: String, default: '' },
        clearable: { type: Boolean, default: false },
    };

    connect() {
        if (this.element.disabled) {
            return;
        }

        const plugins = [];
        if (this.multipleValue) {
            plugins.push('remove_button');
        } else if (this.clearableValue) {
            plugins.push('clear_button');
        }

        const inModal = this.element.closest('.modal');

        this.instance = new TomSelect(this.element, {
            plugins,
            create: false,
            allowEmptyOption: true,
            placeholder: this.placeholderValue || undefined,
            hideSelected: this.multipleValue,
            maxOptions: null,
            wrapperClass: 'ts-wrapper member-select form-select',
            dropdownClass: 'member-select-dropdown',
            ...(inModal ? { dropdownParent: document.body } : {}),
            onDropdownOpen: () => this.element.closest('.ts-wrapper')?.classList.add('dropdown-active'),
            onDropdownClose: () => this.element.closest('.ts-wrapper')?.classList.remove('dropdown-active'),
        });
    }

    disconnect() {
        if (this.instance) {
            this.instance.destroy();
            this.instance = null;
        }
    }
}
