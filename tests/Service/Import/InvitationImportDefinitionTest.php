<?php

namespace App\Tests\Service\Import;

use App\Entity\Club;
use App\Entity\User;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Service\Import\Definition\InvitationImportDefinition;
use App\Service\Import\ImportContext;
use App\Service\InvitationService;
use PHPUnit\Framework\TestCase;

class InvitationImportDefinitionTest extends TestCase
{
    public function testValidateReasons(): void
    {
        $memberships = $this->createStub(MembershipRepository::class);
        $memberships->method('findByEmailAndClub')->willReturnCallback(static fn (string $email) => $email === 'membre@example.test' ? new \App\Entity\Membership() : null);
        $invitations = $this->createStub(InvitationRepository::class);
        $invitations->method('findPendingByEmailAndClub')->willReturnCallback(static fn (string $email) => $email === 'invite@example.test' ? new \App\Entity\Invitation() : null);
        $definition = new InvitationImportDefinition($memberships, $invitations, $this->createStub(InvitationService::class));
        $context = new ImportContext(new Club(), new User());
        $base = ['firstname' => 'A', 'lastname' => 'B', 'isManager' => '', 'isInspector' => '', 'isPilote' => ''];

        $this->assertSame('importReasonInvalidEmail', $definition->validate(['email' => 'pas-un-mail'] + $base, $context));
        $this->assertSame('importReasonAlreadyMember', $definition->validate(['email' => 'membre@example.test'] + $base, $context));
        $this->assertSame('importReasonPendingInvitation', $definition->validate(['email' => 'invite@example.test'] + $base, $context));
        $this->assertNull($definition->validate(['email' => 'jean@example.test'] + $base, $context));
        $this->assertSame('importReasonDuplicateInFile', $definition->validate(['email' => 'JEAN@example.test'] + $base, $context));
    }

    public function testImportUsesOverridesForRoles(): void
    {
        $service = $this->createMock(InvitationService::class);
        $service->expects($this->once())->method('createInvitation')->with($this->anything(), $this->callback(static fn (array $data): bool => $data['email'] === 'jean@example.test' && $data['isInspector'] === true && $data['isManager'] === false))->willReturn(new \App\Entity\Invitation());
        $service->expects($this->once())->method('sendInvitation');
        $definition = new InvitationImportDefinition($this->createStub(MembershipRepository::class), $this->createStub(InvitationRepository::class), $service);
        $context = new ImportContext(new Club(), new User(), [1 => ['isInspector' => '1']]);

        $definition->import(['email' => 'jean@example.test', 'firstname' => 'Jean', 'lastname' => 'Dupont', 'isManager' => 'yes', 'isInspector' => '', 'isPilote' => '', '_line' => '1'], $context);
        // import() ne doit rien envoyer avant que finalize() ne soit appelé (après le commit).
        $definition->finalize($context);
    }

    public function testImportWithSubmittedRowAndNoRoleGrantsNothing(): void
    {
        // Ligne soumise (marqueur _submitted présent) mais aucune case de rôle cochée : le fichier
        // disait « oui » pour isInspector, mais le gestionnaire a décoché à l'aperçu — ne rien accorder.
        $service = $this->createMock(InvitationService::class);
        $service->expects($this->once())->method('createInvitation')->with($this->anything(), $this->callback(static fn (array $data): bool => $data['isInspector'] === false && $data['isManager'] === false && $data['isPilote'] === false))->willReturn(new \App\Entity\Invitation());
        $service->expects($this->once())->method('sendInvitation');
        $definition = new InvitationImportDefinition($this->createStub(MembershipRepository::class), $this->createStub(InvitationRepository::class), $service);
        $context = new ImportContext(new Club(), new User(), [1 => ['_submitted' => '1']]);

        $definition->import(['email' => 'jean@example.test', 'firstname' => 'Jean', 'lastname' => 'Dupont', 'isManager' => '', 'isInspector' => 'yes', 'isPilote' => '', '_line' => '1'], $context);
        $definition->finalize($context);
    }
}
