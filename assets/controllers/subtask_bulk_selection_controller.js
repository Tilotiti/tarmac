import { Controller } from '@hotwired/stimulus';

/**
 * Gestion de la sélection multiple de sous-tâches sur la page de détail d'une tâche.
 *
 * Attendu dans le template :
 * - data-controller="subtask-bulk-selection"
 * - data-subtask-bulk-selection-target="subtaskList" sur le conteneur de la liste
 * - data-subtask-bulk-selection-target="actionsBar" sur la barre d'actions flottante
 * - data-subtask-bulk-selection-target="counter" sur l'élément affichant le nombre sélectionné
 * - data-subtask-bulk-selection-target="selectToggle" sur le bouton "Sélectionner / Annuler"
 * - chaque carte a:
 *   - data-subtask-bulk-selection-target="item"
 *   - data-subtask-bulk-selection-id-value="<id>"
 *   - une checkbox avec data-subtask-bulk-selection-target="checkbox"
 */
export default class extends Controller {
    static targets = ['subtaskList', 'item', 'checkbox', 'actionsBar', 'counter', 'selectToggle', 'selectIcon', 'selectLabel'];
    static values = {
        bulkAddContributionUrl: String,
        bulkCloseUrl: String,
        bulkCancelUrl: String,
        hasBulkAddContribution: Boolean,
        hasBulkClose: Boolean,
        hasBulkCancel: Boolean,
    };

    connect() {
        this.selectedIds = new Set();
        this.selectionMode = false;
        this.boundHandleBulkFormSubmit = this.handleBulkFormSubmit.bind(this);
        // Phase capture pour s'exécuter AVANT Turbo/Hotwire et pouvoir ajouter subTaskIds
        document.addEventListener('submit', this.boundHandleBulkFormSubmit, true);
        this.updateUi();
    }

    disconnect() {
        document.removeEventListener('submit', this.boundHandleBulkFormSubmit, true);
    }

    /**
     * Intercepte le submit des formulaires bulk (peut être déplacé hors scope par Bootstrap).
     */
    handleBulkFormSubmit(event) {
        const form = event.target;
        if (form?.id === 'bulkAddContributionForm') {
            this.prepareBulkAddContribution(event);
            return;
        }
        if (form?.id === 'bulkCloseForm') {
            this.prepareBulkClose(event);
            return;
        }
        if (form?.id === 'bulkCancelForm') {
            this.prepareBulkCancel(event);
            return;
        }
    }

    enterSelectionMode() {
        this.selectionMode = true;
        this.updateUi();
    }

    exitSelectionMode() {
        this.selectionMode = false;
        this.selectedIds.clear();
        this.updateUi();
    }

    toggleSelectionMode() {
        if (this.selectionMode) {
            this.exitSelectionMode();
        } else {
            this.enterSelectionMode();
        }
    }

    onItemClick(event) {
        if (!this.selectionMode) {
            return;
        }

        // En mode sélection, on empêche la navigation et on toggle la checkbox.
        event.preventDefault();
        event.stopPropagation();

        const item = event.currentTarget.closest('[data-subtask-bulk-selection-target="item"]');
        if (!item) return;

        const checkbox = item.querySelector('.subtask-bulk-checkbox input[type="checkbox"]');
        if (!checkbox) return;

        checkbox.checked = !checkbox.checked;
        this.updateSelectionForCheckbox(checkbox);
    }

    onCheckboxClick(event) {
        // Empêche que le clic sur la checkbox déclenche aussi le clic sur la carte.
        event.stopPropagation();
        const checkbox = event.currentTarget;
        this.updateSelectionForCheckbox(checkbox);
    }

    updateSelectionForCheckbox(checkbox) {
        const item = checkbox.closest('[data-subtask-bulk-selection-target="item"]');
        if (!item) return;

        const id = Number.parseInt(item.dataset.subtaskBulkSelectionIdValue ?? '0', 10);
        if (!id) return;

        if (checkbox.checked) {
            this.selectedIds.add(id);
        } else {
            this.selectedIds.delete(id);
        }

        this.updateUi();
    }

    openBulkAddContributionModal() {
        if (!this.hasBulkAddContributionUrlValue || this.selectedIds.size === 0) {
            return;
        }

        const modalEl = document.getElementById('bulkAddContributionModal');
        const form = document.getElementById('bulkAddContributionForm');
        if (!modalEl || !window.bootstrap || !form) return;

        // Stocke les IDs sélectionnés sur le formulaire pour les retrouver au submit
        // (évite les problèmes de scope si Bootstrap déplace la modale)
        form.dataset.selectedSubTaskIds = JSON.stringify(Array.from(this.selectedIds));

        const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    prepareBulkAddContribution(event) {
        const form = event?.target?.id === 'bulkAddContributionForm' ? event.target : document.getElementById('bulkAddContributionForm');
        if (!form) {
            event?.preventDefault?.();
            return;
        }

        // Lecture des IDs depuis le form (stockés à l'ouverture) ou en fallback depuis selectedIds
        let idsToAdd = [];
        try {
            const stored = form.dataset.selectedSubTaskIds;
            if (stored) {
                idsToAdd = JSON.parse(stored);
            }
        } catch {
            // ignore
        }
        if (idsToAdd.length === 0 && this.selectedIds?.size > 0) {
            idsToAdd = Array.from(this.selectedIds);
        }

        if (!this.hasBulkAddContributionUrlValue || idsToAdd.length === 0) {
            event?.preventDefault?.();
            return;
        }

        // Nettoyage des anciens champs subTaskIds s'ils existent.
        [...form.querySelectorAll('input[name="subTaskIds[]"]')].forEach((el) => el.remove());

        // Ajoute un champ hidden subTaskIds[] pour chaque sous-tâche sélectionnée.
        idsToAdd.forEach((id) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'subTaskIds[]';
            hidden.value = String(id);
            form.appendChild(hidden);
        });

        // On laisse le navigateur envoyer le formulaire en POST vers le contrôleur Symfony.
    }

    openBulkCloseModal() {
        if (!this.hasBulkCloseUrlValue || this.selectedIds.size === 0) {
            return;
        }

        const modalEl = document.getElementById('bulkCloseModal');
        const form = document.getElementById('bulkCloseForm');
        if (!modalEl || !window.bootstrap || !form) return;

        // Stocke les IDs sélectionnés sur le formulaire pour les retrouver au submit
        form.dataset.selectedSubTaskIds = JSON.stringify(Array.from(this.selectedIds));

        const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    prepareBulkClose(event) {
        const form = event?.target?.id === 'bulkCloseForm' ? event.target : document.getElementById('bulkCloseForm');
        if (!form) {
            event?.preventDefault?.();
            return;
        }

        let idsToAdd = [];
        try {
            const stored = form.dataset.selectedSubTaskIds;
            if (stored) {
                idsToAdd = JSON.parse(stored);
            }
        } catch {
            // ignore
        }
        if (idsToAdd.length === 0 && this.selectedIds?.size > 0) {
            idsToAdd = Array.from(this.selectedIds);
        }

        if (!this.hasBulkCloseUrlValue || idsToAdd.length === 0) {
            event?.preventDefault?.();
            return;
        }

        [...form.querySelectorAll('input[name="subTaskIds[]"]')].forEach((el) => el.remove());
        idsToAdd.forEach((id) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'subTaskIds[]';
            hidden.value = String(id);
            form.appendChild(hidden);
        });
    }

    openBulkCancelModal() {
        if (!this.hasBulkCancelUrlValue || this.selectedIds.size === 0) {
            return;
        }

        const modalEl = document.getElementById('bulkCancelModal');
        const form = document.getElementById('bulkCancelForm');
        if (!modalEl || !window.bootstrap || !form) return;

        // Stocke les IDs sélectionnés sur le formulaire pour les retrouver au submit
        form.dataset.selectedSubTaskIds = JSON.stringify(Array.from(this.selectedIds));

        const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    prepareBulkCancel(event) {
        const form = event?.target?.id === 'bulkCancelForm' ? event.target : document.getElementById('bulkCancelForm');
        if (!form) {
            event?.preventDefault?.();
            return;
        }

        let idsToAdd = [];
        try {
            const stored = form.dataset.selectedSubTaskIds;
            if (stored) {
                idsToAdd = JSON.parse(stored);
            }
        } catch {
            // ignore
        }
        if (idsToAdd.length === 0 && this.selectedIds?.size > 0) {
            idsToAdd = Array.from(this.selectedIds);
        }

        if (!this.hasBulkCancelUrlValue || idsToAdd.length === 0) {
            event?.preventDefault?.();
            return;
        }

        [...form.querySelectorAll('input[name="subTaskIds[]"]')].forEach((el) => el.remove());
        idsToAdd.forEach((id) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'subTaskIds[]';
            hidden.value = String(id);
            form.appendChild(hidden);
        });
    }

    updateUi() {
        this.toggleSelectionClasses();
        this.updateCounter();
        this.updateActionsBarVisibility();
        this.updateSelectToggleLabel();
    }

    toggleSelectionClasses() {
        this.itemTargets.forEach((item) => {
            item.classList.toggle('is-selectable', this.selectionMode);
            const checkboxWrapper = item.querySelector('.subtask-bulk-checkbox');
            if (!checkboxWrapper) return;

            if (this.selectionMode) {
                checkboxWrapper.classList.add('subtask-bulk-checkbox--visible');
            } else {
                checkboxWrapper.classList.remove('subtask-bulk-checkbox--visible');
                const input = checkboxWrapper.querySelector('input[type="checkbox"]');
                if (input) {
                    input.checked = false;
                }
            }
        });
    }

    updateCounter() {
        if (!this.hasCounterTarget) return;
        this.counterTarget.textContent = String(this.selectedIds.size);
    }

    updateActionsBarVisibility() {
        if (!this.hasActionsBarTarget) return;
        const shouldShow = this.selectionMode && this.selectedIds.size > 0;
        this.actionsBarTarget.classList.toggle('d-none', !shouldShow);
        this.actionsBarTarget.classList.toggle('subtask-bulk-actions--visible', shouldShow);
    }

    updateSelectToggleLabel() {
        if (!this.hasSelectLabelTarget) return;
        const labelKey = this.selectionMode ? 'bulkSelectionCancel' : 'bulkSelectionStart';
        this.selectLabelTarget.textContent = this.translate(labelKey);
        this.updateSelectToggleIcon();
    }

    updateSelectToggleIcon() {
        if (!this.hasSelectIconTarget) return;

        const icon = this.selectIconTarget;
        icon.classList.remove('ti-list-check', 'ti-circle-x');

        if (this.selectionMode) {
            icon.classList.add('ti-circle-x');
        } else {
            icon.classList.add('ti-list-check');
        }
    }

    postJson(url, data, onSuccess) {
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(data),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Request failed');
                }
                return response.json();
            })
            .then((payload) => {
                if (payload && payload.success) {
                    onSuccess?.(payload);
                    return;
                }
                // fallback simple
                window.location.reload();
            })
            .catch(() => {
                window.location.reload();
            });
    }

    translate(key) {
        // Petite couche de secours : on lit les data attributes sur le contrôleur si disponibles.
        const attr = this.element.getAttribute(`data-subtask-bulk-selection-${key}-value`);
        return attr ?? key;
    }
}


