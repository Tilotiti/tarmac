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
    static values = { url: String, emptyText: String };

    connect() {
        this.timer = null;
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
        if (!response.ok) {
            return;
        }
        this.render(await response.json());
    }

    render(items) {
        this.resultsTarget.innerHTML = '';
        if (items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item text-secondary';
            empty.textContent = this.emptyTextValue;
            this.resultsTarget.appendChild(empty);
        }
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.style.minHeight = '44px';
            button.textContent = item.label;
            button.dataset.id = item.id;
            button.dataset.action = 'aerodrome-search#pick';
            this.resultsTarget.appendChild(button);
        });
        this.resultsTarget.hidden = false;
    }

    pick(event) {
        const button = event.currentTarget;
        this.hiddenTarget.value = button.dataset.id;
        this.queryTarget.value = button.textContent;
        this.clearResults();
        if (this.hasManualTarget) {
            this.manualTarget.hidden = true;
            this.manualFieldTarget.value = '';
        }
    }

    toggleManual(event) {
        event.preventDefault();
        this.manualTarget.hidden = !this.manualTarget.hidden;
        if (!this.manualTarget.hidden) {
            this.hiddenTarget.value = '';
            this.queryTarget.value = '';
            this.clearResults();
            this.manualFieldTarget.focus();
        }
    }

    clearResults() {
        this.resultsTarget.innerHTML = '';
        this.resultsTarget.hidden = true;
    }
}
