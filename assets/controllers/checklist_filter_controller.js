import { Controller } from '@hotwired/stimulus';

/**
 * Liste à cocher filtrable : masque les lignes qui ne contiennent pas le texte
 * saisi, compte les cases cochées et désactive l'envoi tant qu'aucune ne l'est.
 */
export default class extends Controller {
    static targets = ['search', 'item', 'empty', 'count', 'submit'];

    connect() {
        this.updateCount();
    }

    filter() {
        const searchValue = this.searchTarget.value.toLowerCase().trim();
        let visibleCount = 0;

        this.itemTargets.forEach(item => {
            const matches = item.textContent.toLowerCase().includes(searchValue);

            item.classList.toggle('d-none', !matches);
            if (matches) {
                visibleCount++;
            }
        });

        if (this.hasEmptyTarget) {
            this.emptyTarget.classList.toggle('d-none', visibleCount !== 0);
        }
    }

    updateCount() {
        const checked = this.element.querySelectorAll('input[type="checkbox"]:checked').length;

        if (this.hasCountTarget) {
            this.countTarget.textContent = checked;
        }

        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = checked === 0;
        }
    }
}
