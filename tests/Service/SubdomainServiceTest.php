<?php

namespace App\Tests\Service;

use App\Service\SubdomainService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

class SubdomainServiceTest extends TestCase
{
    private function service(): SubdomainService
    {
        return new SubdomainService(new RequestStack(), 'tarmac.test');
    }

    public function testReservedWords(): void
    {
        $this->assertTrue($this->service()->isReserved('www'));
        $this->assertTrue($this->service()->isReserved('ADMIN'));
        $this->assertFalse($this->service()->isReserved('acpb'));
    }

    public function testWellFormed(): void
    {
        $this->assertTrue($this->service()->isWellFormed('acpb'));
        $this->assertTrue($this->service()->isWellFormed('aero-club-2'));
        $this->assertFalse($this->service()->isWellFormed('a'));
        $this->assertFalse($this->service()->isWellFormed('-acpb'));
        $this->assertFalse($this->service()->isWellFormed('acpb-'));
        $this->assertFalse($this->service()->isWellFormed('Aéro'));
        $this->assertFalse($this->service()->isWellFormed(str_repeat('a', 31)));

        // Bornes de longueur (2 à 30 caractères) : la regex seule doit les faire respecter.
        $this->assertTrue($this->service()->isWellFormed('ab'));
        $this->assertTrue($this->service()->isWellFormed(str_repeat('a', 30)));
    }
}
