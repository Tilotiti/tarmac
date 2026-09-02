<?php

namespace App\Tests\Service\Onboarding;

use App\Service\Onboarding\ClubNameNormalizer;
use PHPUnit\Framework\TestCase;

class ClubNameNormalizerTest extends TestCase
{
    public function testNormalizeUnifiesAeroclubForms(): void
    {
        $normalizer = new ClubNameNormalizer();

        $this->assertSame('ac pays basque', $normalizer->normalize('Aéro-Club du Pays Basque'));
        $this->assertSame('ac pays basque', $normalizer->normalize('AEROCLUB PAYS BASQUE'));
        $this->assertSame('cvv saint remy', $normalizer->normalize('Centre de Vol à Voile de Saint-Rémy'));
        $this->assertSame('cvv saint remy', $normalizer->normalize('C.V.V. Saint Rémy'));
    }

    public function testSimilarNames(): void
    {
        $normalizer = new ClubNameNormalizer();

        $this->assertTrue($normalizer->isSimilar('Aéro-Club du Pays Basque', 'Aeroclub Pays Basque'));
        $this->assertTrue($normalizer->isSimilar('CVV Saint-Rémy', 'Centre de Vol à Voile de Saint Rémy'));
        $this->assertTrue($normalizer->isSimilar('Planeurs de Bourgogne', 'Les Planeurs de Bourgogne Sud'));
        $this->assertFalse($normalizer->isSimilar('Planeurs de Bourgogne', 'Aéroclub de Bretagne'));
        $this->assertFalse($normalizer->isSimilar('', 'Aéroclub de Bretagne'));
    }
}
