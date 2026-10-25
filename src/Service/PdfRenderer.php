<?php

namespace App\Service;

use Nucleos\DompdfBundle\Factory\DompdfFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class PdfRenderer
{
    public function __construct(
        private readonly DompdfFactoryInterface $dompdfFactory,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Rend le HTML en PDF affiché dans le navigateur.
     *
     * @param float|null $pageNumberBottom distance en points entre le bas de page et « Page x / y », null pour ne pas numéroter
     */
    public function inlineResponse(string $html, string $filename, ?float $pageNumberBottom = null, float $pageNumberSize = 9): Response
    {
        // ponytail: dompdf garde tout le document en mémoire (~6 Ko par nœud HTML) et les 128 Mo par défaut
        // cèdent vers 200 sous-tâches. 256 Mo tiennent ~2x plus ; au-delà, borner le contenu ou changer de moteur.
        ini_set('memory_limit', '256M');

        $dompdf = $this->dompdfFactory->create();
        $dompdf->loadHtml($html);
        $dompdf->render();

        if (null !== $pageNumberBottom) {
            $canvas = $dompdf->getCanvas();
            $canvas->page_text(
                $canvas->get_width() - 100,
                $canvas->get_height() - $pageNumberBottom,
                $this->translator->trans('pdfPageNumber', ['page' => '{PAGE_NUM}', 'pages' => '{PAGE_COUNT}']),
                $dompdf->getFontMetrics()->getFont('Helvetica'),
                $pageNumberSize,
            );
        }

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
