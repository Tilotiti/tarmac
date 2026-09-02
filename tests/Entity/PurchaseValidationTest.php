<?php

namespace App\Tests\Entity;

use App\Entity\Purchase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/**
 * Proves the entity-level constraint attributes on Purchase carry the
 * translation keys (not Symfony's default English messages). The keys
 * actually rendering in French is covered end-to-end by
 * OnboardingClubTest::testNameTooLongShowsSubstitutedLimit for the
 * shared `valueTooLong` key.
 */
class PurchaseValidationTest extends TestCase
{
    public function testNameTooLongAndZeroQuantityUseExplicitMessageKeys(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $purchase = new Purchase();
        $purchase->setName(str_repeat('a', 256));
        $purchase->setQuantity(0);

        $violations = $validator->validate($purchase);

        $messages = [];
        foreach ($violations as $violation) {
            $messages[$violation->getPropertyPath()] = $violation->getMessageTemplate();
        }

        $this->assertSame('valueTooLong', $messages['name'] ?? null);
        $this->assertSame('quantityMinOne', $messages['quantity'] ?? null);
    }
}
