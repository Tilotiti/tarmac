import { Controller } from '@hotwired/stimulus';

/**
 * Propose un sous-domaine à partir du nom du club, affiche l'adresse complète
 * et vérifie la disponibilité en direct.
 *
 * Attendu : data-controller="subdomain-preview", values checkUrl et domain,
 * targets name, subdomain, preview, status.
 */
export default class extends Controller {
    static targets = ['name', 'subdomain', 'preview', 'status', 'join', 'joinClub', 'joinLink'];
    static values = { checkUrl: String, domain: String };

    connect() {
        this.edited = this.subdomainTarget.value !== '';
        this.timer = null;
        this.update();
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    fromName() {
        if (this.edited) {
            return;
        }
        this.subdomainTarget.value = this.slugify(this.nameTarget.value);
        this.update();
    }

    fromSubdomain() {
        this.edited = this.subdomainTarget.value !== '';
        this.update();
    }

    update() {
        const value = this.subdomainTarget.value.trim().toLowerCase();
        this.previewTarget.textContent = `https://${value || '…'}.${this.domainValue}`;
        clearTimeout(this.timer);
        if (this.hasJoinTarget) {
            this.joinTarget.hidden = true;
        }
        if (value === '') {
            this.statusTarget.textContent = '';
            return;
        }
        this.timer = setTimeout(() => this.check(value), 300);
    }

    async check(value) {
        const response = await fetch(`${this.checkUrlValue}?value=${encodeURIComponent(value)}`, { headers: { Accept: 'application/json' } });
        if (!this.element.isConnected) {
            return;
        }
        if (!response.ok) {
            return;
        }
        const data = await response.json();
        this.statusTarget.textContent = data.message;
        this.statusTarget.className = data.available ? 'form-hint text-success' : 'form-hint text-danger';
        this.toggleJoin(data);
    }

    /** Adresse prise par un club actif : l'encart « demander à rejoindre » s'affiche, sinon il se cache. */
    toggleJoin(data) {
        if (!this.hasJoinTarget) {
            return;
        }
        if (data.joinUrl) {
            this.joinClubTarget.textContent = data.clubName || '';
            this.joinLinkTarget.href = data.joinUrl;
            this.joinTarget.hidden = false;
        } else {
            this.joinTarget.hidden = true;
        }
    }

    slugify(text) {
        return text
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 30)
            .replace(/-+$/g, '');
    }
}
