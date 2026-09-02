<?php

namespace App\Form\DataTransformer;

use App\Entity\Aerodrome;
use App\Repository\AerodromeRepository;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @implements DataTransformerInterface<Aerodrome|null, string>
 */
final class AerodromeToIdTransformer implements DataTransformerInterface
{
    public function __construct(private readonly AerodromeRepository $aerodromeRepository)
    {
    }

    public function transform(mixed $value): string
    {
        return $value instanceof Aerodrome ? (string) $value->getId() : '';
    }

    public function reverseTransform(mixed $value): ?Aerodrome
    {
        if ($value === null || $value === '') {
            return null;
        }

        $aerodrome = $this->aerodromeRepository->find((int) $value);
        if ($aerodrome === null) {
            throw new TransformationFailedException(sprintf('Aérodrome %s introuvable', $value));
        }

        return $aerodrome;
    }
}
