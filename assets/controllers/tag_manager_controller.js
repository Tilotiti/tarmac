import { Controller } from '@hotwired/stimulus';

/**
 * Tag manager: search input with autocomplete + selected items as removable tags.
 * Syncs with a hidden <select multiple> so form submission works.
 */
export default class extends Controller {
    static targets = ['select', 'tagsContainer', 'input', 'dropdown'];

    connect() {
        this.select = this.selectTarget;
        this.options = this.parseSelectOptions();
        this.selectedValues = new Set(this.getSelectedValues());
        this.renderTags();
        this.bindInput();
        this.bindDropdown();
        this.bindClickOutside();
    }

    parseSelectOptions() {
        const options = [];
        for (const opt of this.select.querySelectorAll('option')) {
            options.push({
                value: opt.value,
                label: opt.textContent.trim(),
            });
        }
        return options;
    }

    getSelectedValues() {
        const values = [];
        for (const opt of this.select.querySelectorAll('option:checked')) {
            values.push(opt.value);
        }
        return values;
    }

    renderTags() {
        this.tagsContainerTarget.innerHTML = '';
        for (const value of this.selectedValues) {
            const option = this.options.find((o) => o.value === value);
            if (option) {
                this.tagsContainerTarget.appendChild(this.createTagEl(option));
            }
        }
    }

    createTagEl(option) {
        const span = document.createElement('span');
        span.className = 'badge bg-primary-lt text-primary me-1 mb-1 d-inline-flex align-items-center';
        span.dataset.value = option.value;
        span.dataset.action = 'click->tag-manager#removeTag';
        span.innerHTML = `${this.escapeHtml(option.label)} <i class="ti ti-x ms-1" style="cursor: pointer; font-size: 0.9em;"></i>`;
        return span;
    }

    removeTag(event) {
        event.preventDefault();
        const tag = event.currentTarget;
        const value = tag.dataset.value;
        this.selectedValues.delete(value);
        this.setSelectValue(value, false);
        tag.remove();
    }

    addTag(option) {
        if (this.selectedValues.has(option.value)) return;
        this.selectedValues.add(option.value);
        this.setSelectValue(option.value, true);
        this.tagsContainerTarget.appendChild(this.createTagEl(option));
    }

    setSelectValue(value, selected) {
        const opt = this.select.querySelector(`option[value="${this.escapeAttr(value)}"]`);
        if (opt) opt.selected = selected;
    }

    bindInput() {
        if (!this.hasInputTarget) return;
        const input = this.inputTarget;
        input.addEventListener('focus', () => this.showDropdown());
        input.addEventListener('input', () => this.filterDropdown());
        input.addEventListener('keydown', (e) => this.handleKeydown(e));
    }

    bindDropdown() {
        if (!this.hasDropdownTarget) return;
        this.dropdownTarget.addEventListener('click', (e) => {
            const item = e.target.closest('[data-tag-manager-option-value]');
            if (item) {
                e.preventDefault();
                const value = item.dataset.tagManagerOptionValue;
                const label = item.dataset.tagManagerOptionLabel || item.textContent.trim();
                this.addTag({ value, label });
                this.inputTarget.value = '';
                this.filterDropdown();
            }
        });
    }

    showDropdown() {
        this.filterDropdown();
    }

    filterDropdown() {
        if (!this.hasDropdownTarget) return;
        const q = (this.hasInputTarget ? this.inputTarget.value : '').trim().toLowerCase();
        const available = this.options.filter(
            (o) => !this.selectedValues.has(o.value) && (!q || o.label.toLowerCase().includes(q))
        );
        this.dropdownTarget.innerHTML = '';
        if (available.length === 0) {
            this.dropdownTarget.classList.add('d-none');
            return;
        }
        this.dropdownTarget.classList.remove('d-none');
        for (const opt of available) {
            const div = document.createElement('a');
            div.href = '#';
            div.className = 'list-group-item list-group-item-action py-2 px-3';
            div.role = 'button';
            div.tabIndex = 0;
            div.dataset.tagManagerOptionValue = opt.value;
            div.dataset.tagManagerOptionLabel = opt.label;
            div.textContent = opt.label;
            this.dropdownTarget.appendChild(div);
        }
    }

    handleKeydown(event) {
        if (event.key === 'Escape') {
            this.dropdownTarget?.classList.add('d-none');
        }
    }

    bindClickOutside() {
        document.addEventListener('click', (e) => {
            if (this.hasDropdownTarget && this.element && !this.element.contains(e.target)) {
                this.dropdownTarget.classList.add('d-none');
            }
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
}
