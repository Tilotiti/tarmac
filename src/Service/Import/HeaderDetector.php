<?php

namespace App\Service\Import;

/**
 * Devine si la première ligne contient des titres : que du texte court, sans vide ni doublon,
 * au moins une ligne de données dessous, et aucune colonne où le titre supposé répète mot pour
 * mot la valeur de la ligne suivante (un intitulé de colonne ne coïncide pas avec une donnée).
 */
final class HeaderDetector
{
    public function looksLikeHeader(ImportSheet $sheet): bool
    {
        if ($sheet->rowCount() < 2) {
            return false;
        }
        $first = $sheet->rows()[0];
        $nonEmpty = array_filter($first, static fn (string $cell): bool => $cell !== '');
        if (count($nonEmpty) !== count($first) || count(array_unique($first)) !== count($first)) {
            return false;
        }
        foreach ($first as $cell) {
            if (is_numeric($cell) || mb_strlen($cell) > 60) {
                return false;
            }
        }
        $second = $sheet->rows()[1];
        foreach ($first as $index => $cell) {
            if ($cell !== '' && ($second[$index] ?? null) === $cell) {
                return false;
            }
        }

        return true;
    }
}
