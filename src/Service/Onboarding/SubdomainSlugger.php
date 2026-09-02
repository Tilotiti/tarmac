<?php

namespace App\Service\Onboarding;

use Symfony\Component\String\Slugger\AsciiSlugger;

final class SubdomainSlugger
{
    public const MAX_LENGTH = 30;

    public function fromName(string $name): string
    {
        $slug = (new AsciiSlugger('fr'))->slug($name, '-')->lower()->toString();

        return trim(substr($slug, 0, self::MAX_LENGTH), '-');
    }
}
