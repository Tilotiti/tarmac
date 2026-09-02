import { Controller } from '@hotwired/stimulus';

/**
 * Écran de rapprochement des colonnes : sous chaque menu, l'aperçu des valeurs de la colonne choisie ;
 * pour un champ à choix sans colonne, le menu de valeur unique apparaît.
 *
 * Attendu : data-controller="import-mapping", data-import-mapping-samples-value (JSON {index: [valeurs]}),
 * targets select / sample / fixed portant data-field.
 */
export default class extends Controller {
    static targets = ['select', 'sample', 'fixed'];
    static values = { samples: Object, sampleLabel: String };

    update(event) {
        const field = event.currentTarget.dataset.field;
        const index = event.currentTarget.value;
        const sample = this.sampleTargets.find((element) => element.dataset.field === field);
        if (sample) {
            const values = index === '' ? [] : (this.samplesValue[index] || []);
            sample.textContent = values.length ? `${this.element.dataset.importMappingSampleLabelValue || ''} ${values.join(' · ')}`.trim() : '';
        }
        const fixed = this.fixedTargets.find((element) => element.dataset.field === field);
        if (fixed) {
            fixed.hidden = index !== '';
        }
    }
}
