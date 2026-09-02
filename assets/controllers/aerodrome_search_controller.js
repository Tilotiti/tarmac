import { Controller } from '@hotwired/stimulus';

/**
 * Autocomplétion d'aérodrome.
 *
 * Attendu dans le template (voir component/aerodromeSelector.html.twig) :
 * - data-controller="aerodrome-search" avec data-aerodrome-search-url-value (endpoint JSON ?q=)
 *   et data-aerodrome-search-empty-text-value
 * - targets : query (texte saisi), hidden (id), results (liste), manual (bloc commune), manualField
 */
export default class extends Controller {
    static targets = ['query', 'hidden', 'results', 'manual', 'manualField'];
    static values = { url: String, emptyText: String, manualMode: Boolean };

    connect() {
        this.timer = null;
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    search() {
        clearTimeout(this.timer);
        this.hiddenTarget.value = '';
        const query = this.queryTarget.value.trim();
        if (query.length < 2) {
            this.clearResults();
            return;
        }
        this.timer = setTimeout(() => this.fetchResults(query), 250);
    }

    async fetchResults(query) {
        const response = await fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
        if (!this.element.isConnected) {
            return;
        }
        if (!response.ok) {
            return;
        }
        this.render(await response.json());
    }

    render(items) {
        this.resultsTarget.innerHTML = '';
        if (items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'aerodrome-results__empty';
            empty.textContent = this.emptyTextValue;
            this.resultsTarget.appendChild(empty);
        }
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'aerodrome-results__item';
            button.dataset.id = item.id;
            button.dataset.label = item.label;
            button.dataset.action = 'aerodrome-search#pick';

            const name = document.createElement('span');
            name.className = 'aerodrome-results__name';
            name.textContent = item.name;
            button.appendChild(name);

            const meta = [item.municipality, item.icaoCode].filter(Boolean).join(' · ');
            if (meta !== '') {
                const details = document.createElement('span');
                details.className = 'aerodrome-results__meta';
                details.textContent = meta;
                button.appendChild(details);
            }
            this.resultsTarget.appendChild(button);
        });
        this.resultsTarget.hidden = false;
    }

    pick(event) {
        const button = event.currentTarget;
        this.hiddenTarget.value = button.dataset.id;
        this.queryTarget.value = button.dataset.label;
        this.clearResults();
        if (this.hasManualTarget) {
            this.manualTarget.hidden = true;
        }
        if (this.hasManualFieldTarget) {
            this.manualFieldTarget.value = '';
        }
    }

    toggleManual(event) {
        event.preventDefault();
        this.manualTarget.hidden = !this.manualTarget.hidden;
        // Mode contact (public) : on montre la note sans toucher à la sélection en cours
        if (!this.manualModeValue || this.manualTarget.hidden) {
            return;
        }
        this.hiddenTarget.value = '';
        this.queryTarget.value = '';
        this.clearResults();
        if (this.hasManualFieldTarget) {
            this.manualFieldTarget.focus();
        }
    }

    clearResults() {
        this.resultsTarget.innerHTML = '';
        this.resultsTarget.hidden = true;
    }
}
