import { Controller } from '@hotwired/stimulus';

/**
 * Réduit les photos côté navigateur avant l'envoi.
 *
 * Une photo de téléphone fait couramment 4032×3024 pour 4 à 5 Mo. Envoyer cinq
 * clichés bruts en 4G prend une éternité et bute sur post_max_size. On se
 * contente de ramener le côté long à maxEdge (3000 px par défaut) : le facteur
 * de réduction reste faible (1,34 sur une photo 12 Mpx), donc une fissure fine
 * survit au pinch-zoom — ce qui est tout l'intérêt d'une photo d'anomalie.
 *
 * Principes :
 * - on ne touche jamais une image déjà sous maxEdge, ni un format non bitmap ;
 * - l'orientation EXIF est appliquée avant le rendu, sinon les photos prises en
 *   portrait ressortiraient couchées (le canvas ne conserve pas l'EXIF) ;
 * - si le résultat n'est pas plus léger que l'original, on garde l'original ;
 * - toute erreur laisse le fichier d'origine dans l'input : on dégrade vers le
 *   comportement d'avant, jamais vers une perte de photo.
 *
 * Template :
 *   <div data-controller="image-downscale">
 *       <input type="file" multiple data-action="change->image-downscale#process">
 *   </div>
 */
const RESIZABLE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

export default class extends Controller {
    static values = {
        maxEdge: { type: Number, default: 3000 },
        quality: { type: Number, default: 0.9 },
        busyLabel: String,
    };

    static targets = ['status'];

    async process(event) {
        const input = event.target;
        const files = Array.from(input.files ?? []);

        if (files.length === 0) {
            return;
        }

        // Garde-fou anti-boucle : on réécrit l'input, ce qui redéclenche `change`.
        if (this.processing) {
            return;
        }

        this.processing = true;
        this.setBusy(true, files.length);

        try {
            const processed = [];
            let shrunk = 0;

            for (const file of files) {
                const result = await this.downscale(file);
                processed.push(result);

                if (result !== file) {
                    shrunk++;
                }
            }

            if (shrunk > 0) {
                const transfer = new DataTransfer();
                processed.forEach((file) => transfer.items.add(file));
                input.files = transfer.files;
            }
        } catch {
            // On laisse l'input tel quel : les fichiers d'origine partiront.
        } finally {
            this.setBusy(false, files.length);
            this.processing = false;
        }
    }

    /**
     * @returns {Promise<File>} la version réduite, ou le fichier d'origine
     */
    async downscale(file) {
        if (!RESIZABLE_TYPES.includes(file.type)) {
            return file;
        }

        let bitmap;

        try {
            // from-image : applique l'orientation EXIF, sans quoi les photos
            // prises en portrait ressortent couchées.
            bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch {
            return file;
        }

        const { width, height } = bitmap;
        const longestEdge = Math.max(width, height);

        if (longestEdge <= this.maxEdgeValue) {
            bitmap.close?.();
            return file;
        }

        const ratio = this.maxEdgeValue / longestEdge;
        const targetWidth = Math.round(width * ratio);
        const targetHeight = Math.round(height * ratio);

        let blob;

        try {
            const canvas = document.createElement('canvas');
            canvas.width = targetWidth;
            canvas.height = targetHeight;

            const context = canvas.getContext('2d');
            context.imageSmoothingEnabled = true;
            context.imageSmoothingQuality = 'high';
            context.drawImage(bitmap, 0, 0, targetWidth, targetHeight);

            blob = await new Promise((resolve) => {
                canvas.toBlob(resolve, 'image/jpeg', this.qualityValue);
            });
        } catch {
            return file;
        } finally {
            bitmap.close?.();
        }

        // Un PNG de capture d'écran peut grossir en repassant par le JPEG.
        if (!blob || blob.size >= file.size) {
            return file;
        }

        return new File([blob], this.jpegName(file.name), {
            type: 'image/jpeg',
            lastModified: file.lastModified,
        });
    }

    jpegName(name) {
        return name.replace(/\.[^.]+$/, '') + '.jpg';
    }

    setBusy(busy, count) {
        const submits = this.element.closest('form')?.querySelectorAll('button[type="submit"]') ?? [];

        submits.forEach((button) => {
            button.disabled = busy;
        });

        if (!this.hasStatusTarget) {
            return;
        }

        if (busy) {
            this.statusTarget.hidden = false;
            this.statusTarget.textContent = (this.busyLabelValue || 'Préparation des photos…').replace('{count}', String(count));
        } else {
            this.statusTarget.hidden = true;
            this.statusTarget.textContent = '';
        }
    }
}
