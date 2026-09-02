<?php

namespace App\Controller;

use App\Entity\Aerodrome;
use App\Repository\AerodromeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AerodromeApiController extends AbstractController
{
    #[Route('/api/aerodromes', name: 'api_aerodromes', methods: ['GET'])]
    public function search(Request $request, AerodromeRepository $aerodromeRepository): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        if (mb_strlen($query) < 2) {
            return $this->json([]);
        }

        return $this->json(array_map(
            static fn (Aerodrome $aerodrome): array => [
                'id' => $aerodrome->getId(),
                'label' => $aerodrome->getLabel(),
                'name' => $aerodrome->getName(),
                'municipality' => $aerodrome->getMunicipality(),
                'icaoCode' => $aerodrome->getIcaoCode(),
            ],
            $aerodromeRepository->search($query),
        ));
    }
}
