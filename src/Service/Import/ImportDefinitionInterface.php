<?php

namespace App\Service\Import;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.import_definition')]
interface ImportDefinitionInterface
{
    /** Segment d'URL : equipments, invitations. */
    public function key(): string;

    public function titleKey(): string;

    /** Route de la liste vers laquelle revenir. */
    public function listRoute(): string;

    /** Template Twig de l'écran d'aperçu (étend club/import/preview.html.twig). */
    public function previewTemplate(): string;

    /** @return ImportField[] */
    public function fields(): array;

    /** @return ImportField[] champs affichés en colonnes simples dans l'aperçu (sans les rôles à cocher) */
    public function previewFields(): array;

    /** @param array<string, string> $values valeurs résolues par clé de champ ; renvoie la clé de raison d'ignorance, ou null */
    public function validate(array $values, ImportContext $context): ?string;

    /** @param array<string, string> $values */
    public function import(array $values, ImportContext $context): void;
}
