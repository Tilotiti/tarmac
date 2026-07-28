import { Controller } from '@hotwired/stimulus';

/**
 * Sélection multiple d'anomalies sur la page de liste.
 *
 * Attendu dans le template :
 * - data-controller="anomaly-bulk-selection" sur un wrapper englobant la liste,
 *   la barre d'actions ET les modales
 * - data-anomaly-bulk-selection-target="actionsBar" sur la barre flottante
 * - data-anomaly-bulk-selection-target="counter" sur le compteur
 * - data-anomaly-bulk-selection-target="selectToggle" / "selectIcon" / "selectLabel"
 * - chaque carte :
 *   - data-anomaly-bulk-selection-target="item"
 *   - data-anomaly-bulk-selection-id-value="<id>"
 *   - une checkbox avec data-anomaly-bulk-selection-target="checkbox"
 *
 * Les formulaires de masse portent un id listé dans BULK_FORM_IDS et reçoivent
 * les identifiants sélectionnés sous forme de champs cachés `anomalyIds[]`.
 */
const BULK_FORM_IDS = ['bulkTreatAnomaliesForm', 'bulkIgnoreAnomaliesForm'];

export default class extends Controller {
    static targets = ['item', 'checkbox', 'actionsBar', 'counter', 'selectToggle', 'selectIcon', 'selectLabel'];

    connect() {
        this.selectedIds = new Set();
        this.selectionMode = false;
        this.boundHandleBulkFormSubmit = this.handleBulkFormSubmit.bind(this);
        // Phase capture pour passer AVANT Turbo et pouvoir injecter anomalyIds[]
        document.addEventListener('submit', this.boundHandleBulkFormSubmit, true);
        this.updateUi();
    }

    disconnect() {
        document.removeEventListener('submit', this.boundHandleBulkFormSubmit, true);
    }

    handleBulkFormSubmit(event) {
        const form = event.target;

        if (!form || !BULK_FORM_IDS.includes(form.id)) {
            return;
        }

        let ids = [];

        try {
            const stored = form.dataset.selectedAnomalyIds;
            if (stored) {
                ids = JSON.parse(stored);
            }
        } catch {
            // ignore
        }

        if (ids.length === 0 && this.selectedIds?.size > 0) {
            ids = Array.from(this.selectedIds);
        }

        if (ids.length === 0) {
            event.preventDefault();
            return;
        }

        [...form.querySelectorAll('input[name="anomalyIds[]"]')].forEach((el) => el.remove());

        ids.forEach((id) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'anomalyIds[]';
            hidden.value = String(id);
            form.appendChild(hidden);
        });
    }

    openModal(event) {
        const modalId = event.currentTarget?.dataset?.modalId;
        const formId = event.currentTarget?.dataset?.formId;

        if (!modalId || !formId || this.selectedIds.size === 0) {
            return;
        }

        const modalEl = document.getElementById(modalId);
        const form = document.getElementById(formId);

        if (!modalEl || !form || !window.bootstrap) {
            return;
        }

        // Les IDs sont stockés sur le formulaire : Bootstrap peut déplacer la
        // modale hors du scope Stimulus au moment de l'ouverture.
        form.dataset.selectedAnomalyIds = JSON.stringify(Array.from(this.selectedIds));

        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
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

        // En mode sélection, on empêche la navigation et on bascule la checkbox.
        event.preventDefault();
        event.stopPropagation();

        const item = event.currentTarget.closest('[data-anomaly-bulk-selection-target="item"]');
        if (!item) return;

        const checkbox = item.querySelector('.anomaly-bulk-checkbox input[type="checkbox"]');
        if (!checkbox) return;

        checkbox.checked = !checkbox.checked;
        this.updateSelectionForCheckbox(checkbox);
    }

    onCheckboxClick(event) {
        event.stopPropagation();
        this.updateSelectionForCheckbox(event.currentTarget);
    }

    updateSelectionForCheckbox(checkbox) {
        const item = checkbox.closest('[data-anomaly-bulk-selection-target="item"]');
        if (!item) return;

        const id = Number.parseInt(item.dataset.anomalyBulkSelectionIdValue ?? '0', 10);
        if (!id) return;

        if (checkbox.checked) {
            this.selectedIds.add(id);
        } else {
            this.selectedIds.delete(id);
        }

        this.updateUi();
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

            const checkboxWrapper = item.querySelector('.anomaly-bulk-checkbox');
            if (!checkboxWrapper) return;

            if (this.selectionMode) {
                checkboxWrapper.classList.add('anomaly-bulk-checkbox--visible');
            } else {
                checkboxWrapper.classList.remove('anomaly-bulk-checkbox--visible');
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
        this.actionsBarTarget.classList.toggle('anomaly-bulk-actions--visible', shouldShow);
    }

    updateSelectToggleLabel() {
        if (this.hasSelectLabelTarget) {
            const labelKey = this.selectionMode ? 'bulkSelectionCancel' : 'bulkSelectionStart';
            this.selectLabelTarget.textContent = this.element.getAttribute(`data-anomaly-bulk-selection-${labelKey}-value`) ?? labelKey;
        }

        if (this.hasSelectIconTarget) {
            const icon = this.selectIconTarget;
            icon.classList.remove('ti-list-check', 'ti-circle-x');
            icon.classList.add(this.selectionMode ? 'ti-circle-x' : 'ti-list-check');
        }
    }
}
