import { Controller } from '@hotwired/stimulus';

/**
 * Écran de rapprochement des colonnes : sous chaque menu, l'aperçu des valeurs de la colonne choisie ;
 *
 * Attendu : data-controller="import-mapping", data-import-mapping-samples-value (JSON {index: [valeurs]}),
 * targets select / sample portant data-field.
 */
export default class extends Controller {
    static targets = ['select', 'sample'];
    static values = { samples: Object, sampleLabel: String };

    update(event) {
        const field = event.currentTarget.dataset.field;
        const index = event.currentTarget.value;
        const sample = this.sampleTargets.find((element) => element.dataset.field === field);
        if (sample) {
            const values = index === '' ? [] : (this.samplesValue[index] || []);
            sample.textContent = values.length ? `${this.element.dataset.importMappingSampleLabelValue || ''} ${values.join(' · ')}`.trim() : '';
        }
    }
}
