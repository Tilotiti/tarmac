<?php

namespace App\Service\Onboarding;

use function Symfony\Component\String\u;

/**
 * Ramène deux graphies d'un même club à une forme comparable :
 * « Aéro-Club du Pays Basque » et « AEROCLUB PAYS BASQUE » donnent « ac pays basque ».
 */
final class ClubNameNormalizer
{
    private const PHRASES = [
        'centre de vol a voile' => 'cvv',
        'club de vol a voile' => 'cvv',
        'c v v' => 'cvv',
        'aero club' => 'ac',
        'aeroclub' => 'ac',
    ];

    private const STOP_WORDS = ['de', 'du', 'des', 'le', 'la', 'les', 'd', 'l', 'et'];

    private const SIMILARITY_THRESHOLD = 0.25;

    public function normalize(string $name): string
    {
        $text = u($name)->ascii()->lower()->toString();
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';
        $text = ' ' . trim($text) . ' ';

        foreach (self::PHRASES as $phrase => $token) {
            $text = str_replace(' ' . $phrase . ' ', ' ' . $token . ' ', $text);
        }

        $words = array_filter(
            explode(' ', trim($text)),
            static fn (string $word): bool => $word !== '' && !in_array($word, self::STOP_WORDS, true)
        );

        return implode(' ', $words);
    }

    public function isSimilar(string $first, string $second): bool
    {
        $first = $this->normalize($first);
        $second = $this->normalize($second);

        if ($first === '' || $second === '') {
            return false;
        }
        if (str_contains($first, $second) || str_contains($second, $first)) {
            return true;
        }

        return levenshtein($first, $second) / max(strlen($first), strlen($second)) < self::SIMILARITY_THRESHOLD;
    }
}
