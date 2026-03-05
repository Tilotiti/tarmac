import { Controller } from '@hotwired/stimulus';

/**
 * Filtre les options du select "membre" dans le formulaire de clôture :
 * - Désactive et masque les membres déjà utilisés (contributions existantes + autres lignes)
 * - Masque le bouton "Enregistrer une contribution" quand il ne reste plus de membre disponible
 *
 * Interaction volontairement minimale : on ne modifie que disabled/hidden des options, pas le DOM.
 */
export default class extends Controller {
    static targets = ['container', 'addButton'];
    static values = {
        allMembershipIds: Array,
        initialUsedMembershipIds: { type: Array, default: [] },
    };

    connect() {
        this.usedIds = new Set(this.getInitialUsedIds().map(String));
        this.boundRefresh = this.refresh.bind(this);
        this.refresh();
        this.observeContainer();
        if (this.hasContainerTarget) {
            this.containerTarget.addEventListener('change', this.boundRefresh);
        }
    }

    getInitialUsedIds() {
        if (this.hasContainerTarget) {
            const raw = (this.containerTarget.dataset.initialUsedIds || '').split(',').map((s) => s.trim()).filter(Boolean);
            if (raw.length > 0) return raw;
        }
        const v = this.initialUsedMembershipIdsValue;
        if (Array.isArray(v) && v.length > 0) return v;
        try {
            const s = this.element.dataset.contributionMembersFilterInitialUsedMembershipIdsValue;
            if (typeof s === 'string' && s) {
                const parsed = JSON.parse(s);
                return Array.isArray(parsed) ? parsed : [];
            }
        } catch (_) {}
        return [];
    }

    getAllMembershipIds() {
        const v = this.allMembershipIdsValue;
        if (Array.isArray(v) && v.length > 0) return v;
        try {
            const s = this.element.dataset.contributionMembersFilterAllMembershipIdsValue;
            if (typeof s === 'string' && s) {
                const parsed = JSON.parse(s);
                return Array.isArray(parsed) ? parsed : [];
            }
        } catch (_) {}
        return [];
    }

    disconnect() {
        if (this.observer) this.observer.disconnect();
        if (this.hasContainerTarget && this.boundRefresh) {
            this.containerTarget.removeEventListener('change', this.boundRefresh);
        }
    }

    /** Ne réagit qu'à l'ajout/suppression de lignes (enfants directs), pas aux changements dans les selects. */
    observeContainer() {
        if (!this.hasContainerTarget) return;
        this.observer = new MutationObserver(() => {
            requestAnimationFrame(() => this.refresh());
        });
        this.observer.observe(this.containerTarget, { childList: true, subtree: false });
    }

    refresh() {
        this.collectUsedIds();
        this.filterSelects();
        this.toggleAddButton();
    }

    collectUsedIds() {
        if (!this.hasContainerTarget) return;
        const used = new Set(this.getInitialUsedIds().map(String));

        this.containerTarget.querySelectorAll('select[name*="membership"]').forEach((select) => {
            const v = select.value?.trim();
            if (v) used.add(v);
        });

        this.usedIds = used;
    }

    /**
     * Désactive et masque les options des membres déjà utilisés (autres lignes).
     * Pour chaque select, "utilisés par les autres" = initial + inputs + valeurs des *autres* selects
     * (pas le sien), pour : 1) ne pas réinitialiser quand l'utilisateur vient de choisir ;
     * 2) réinitialiser une nouvelle ligne si sa valeur par défaut est déjà prise.
     */
    filterSelects() {
        if (!this.hasContainerTarget) return;
        const selects = this.containerTarget.querySelectorAll('select[name*="membership"]');

        selects.forEach((select) => {
            // Existing contributions keep their disabled member select;
            // never mutate their value/options.
            if (select.disabled) return;

            const usedByOthers = new Set(this.getInitialUsedIds().map(String));
            selects.forEach((other) => {
                if (other === select) return;
                const v = other.value?.trim();
                if (v) usedByOthers.add(v);
            });

            const selectedValue = select.value?.trim();
            let firstAvailable = null;

            select.querySelectorAll('option').forEach((option) => {
                const val = option.value?.trim();
                if (!val) {
                    if (firstAvailable === null) firstAvailable = '';
                    return;
                }
                if (usedByOthers.has(val)) {
                    option.disabled = true;
                    option.hidden = true;
                } else {
                    option.disabled = false;
                    option.hidden = false;
                    if (firstAvailable === null) firstAvailable = val;
                }
            });

            if (selectedValue && usedByOthers.has(selectedValue)) {
                select.value = firstAvailable ?? '';
            }
        });
    }

    toggleAddButton() {
        if (!this.hasAddButtonTarget) return;
        const all = new Set(this.getAllMembershipIds().map(String));
        const available = [...all].filter((id) => !this.usedIds.has(id));
        this.addButtonTarget.style.display = available.length > 0 ? '' : 'none';
    }
}
