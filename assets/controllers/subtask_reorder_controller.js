import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

/**
 * Mode « Réorganiser » des sous-tâches sur la page de détail d'une tâche.
 *
 * Monté sur le même wrapper que subtask-bulk-selection, avec lequel il s'exclut
 * mutuellement (via les événements subtask-reorder:start / subtask-bulk-selection:start
 * déclarés en data-action sur ce wrapper) : les deux barres flottantes ne doivent
 * jamais coexister.
 *
 * Attendu dans le template :
 * - data-controller="subtask-reorder"
 * - data-subtask-reorder-target="list" sur le conteneur de la liste
 * - data-subtask-reorder-target="item" + data-subtask-reorder-id-value="<id>" sur chaque carte,
 *   chacune contenant une poignée .subtask-reorder-handle
 * - data-subtask-reorder-target="toggle" / "icon" / "label" sur le bouton d'activation
 * - data-subtask-reorder-target="actionsBar" sur la barre flottante de validation
 * - data-subtask-reorder-target="form" sur le formulaire caché de soumission
 */
export default class extends Controller {
    static targets = ['list', 'item', 'toggle', 'icon', 'label', 'actionsBar', 'form'];

    connect() {
        this.reorderMode = false;
        this.sortable = null;
        this.initialOrder = [];
        this.updateUi();
    }

    disconnect() {
        this.destroySortable();
    }

    toggleReorderMode() {
        if (this.reorderMode) {
            this.exit();
        } else {
            this.enter();
        }
    }

    enter() {
        if (this.reorderMode || !this.hasListTarget || this.itemTargets.length < 2) {
            return;
        }

        // Mémorisé pour pouvoir restaurer le DOM si l'utilisateur annule.
        this.initialOrder = this.itemTargets.slice();

        this.sortable = Sortable.create(this.listTarget, {
            draggable: '[data-subtask-reorder-target="item"]',
            handle: '.subtask-reorder-handle',
            animation: 150,
            // Au doigt, un court appui évite de déclencher un glissement pendant un scroll.
            delay: 120,
            delayOnTouchOnly: true,
            touchStartThreshold: 5,
            ghostClass: 'subtask-reorder-ghost',
            chosenClass: 'subtask-reorder-chosen',
        });

        this.reorderMode = true;
        this.updateUi();
        this.dispatch('start');
    }

    exit() {
        if (!this.reorderMode) {
            return;
        }

        this.destroySortable();

        // Annulation : on remet le DOM dans son ordre d'origine, sans aucune requête.
        this.initialOrder.forEach((item) => this.listTarget.appendChild(item));
        this.initialOrder = [];

        this.reorderMode = false;
        this.updateUi();
    }

    save() {
        if (!this.reorderMode || !this.hasFormTarget) {
            return;
        }

        const ids = this.itemTargets
            .map((item) => item.dataset.subtaskReorderIdValue)
            .filter((id) => id);

        if (ids.length < 2) {
            this.exit();
            return;
        }

        const form = this.formTarget;
        [...form.querySelectorAll('input[name="subTaskIds[]"]')].forEach((el) => el.remove());
        ids.forEach((id) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'subTaskIds[]';
            hidden.value = String(id);
            form.appendChild(hidden);
        });

        form.requestSubmit();
    }

    destroySortable() {
        this.sortable?.destroy();
        this.sortable = null;
    }

    updateUi() {
        this.element.classList.toggle('is-reordering', this.reorderMode);

        if (this.hasActionsBarTarget) {
            this.actionsBarTarget.classList.toggle('d-none', !this.reorderMode);
            this.actionsBarTarget.classList.toggle('subtask-reorder-actions--visible', this.reorderMode);
        }

        if (this.hasLabelTarget) {
            this.labelTarget.textContent = this.translate(this.reorderMode ? 'reorderCancel' : 'reorderStart');
        }

        if (this.hasIconTarget) {
            this.iconTarget.classList.remove('ti-arrows-sort', 'ti-circle-x');
            this.iconTarget.classList.add(this.reorderMode ? 'ti-circle-x' : 'ti-arrows-sort');
        }
    }

    translate(key) {
        const attr = this.element.getAttribute(`data-subtask-reorder-${key}-value`);
        return attr ?? key;
    }
}
