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

    /**
     * Appelé une fois, après que la transaction d'import a été validée (commit inclus), pour les
     * effets de bord qui ne doivent pas participer à la transaction (ex. envoi d'e-mails) : un
     * envoi avant commit enverrait des invitations pour des lignes qu'un rollback effacerait.
     * L'import lui-même est déjà acquis à ce stade (commit fait) : une erreur ici (ex. un envoi
     * d'e-mail qui échoue) ne doit ni relancer, ni interrompre les effets suivants pour les autres
     * lignes — chaque implémentation absorbe ses propres échecs et retourne leur nombre.
     *
     * @return int nombre d'éléments dont l'effet de bord a échoué (0 si tout s'est bien passé)
     */
    public function finalize(ImportContext $context): int;
}
