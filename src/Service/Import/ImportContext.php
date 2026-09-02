<?php

namespace App\Service\Import;

use App\Entity\Club;
use App\Entity\User;

/** Contexte d'un import : club, importateur, mémoire des clés déjà vues (doublons dans le fichier), options de l'aperçu. */
final class ImportContext
{
    private array $seen = [];
    private array $memo = [];

    /** @var array<int, object> entités créées pendant import(), traitées ensuite par finalize() (ex. invitations à envoyer) */
    public array $created = [];

    /** @param array<int, array<string, mixed>> $overrides choix faits sur l'écran d'aperçu, par numéro de ligne */
    public function __construct(public readonly Club $club, public readonly User $user, public array $overrides = [])
    {
    }

    /** Vrai la première fois qu'une clé est vue. */
    public function markSeen(string $key): bool
    {
        if (isset($this->seen[$key])) {
            return false;
        }
        $this->seen[$key] = true;

        return true;
    }

    /**
     * Mémoïse le résultat de $factory pour la durée du contexte (un import) : évite de relancer
     * une requête coûteuse (ex. e-mails existants) à chaque ligne.
     */
    public function remember(string $key, callable $factory): mixed
    {
        if (!array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $factory();
        }

        return $this->memo[$key];
    }
}
