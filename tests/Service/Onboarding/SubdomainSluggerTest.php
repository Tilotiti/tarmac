<?php

namespace App\Tests\Service\Onboarding;

use App\Service\Onboarding\SubdomainSlugger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SubdomainSluggerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function names(): iterable
    {
        yield 'accents et tirets' => ['Aéro-Club du Pays Basque', 'aero-club-du-pays-basque'];
        yield 'apostrophe' => ["L'Élan Vélivole", 'l-elan-velivole'];
        yield 'espaces multiples' => ['CVV   Saint-Rémy ', 'cvv-saint-remy'];
        yield 'troncature sans tiret final' => ['Association Sportive de Vol à Voile de Bourgogne', 'association-sportive-de-vol-a'];
    }

    #[DataProvider('names')]
    public function testFromName(string $name, string $expected): void
    {
        $this->assertSame($expected, (new SubdomainSlugger())->fromName($name));
    }
}
