<?php

namespace App\Tests\Security;

use App\Entity\Club;
use App\Entity\Membership;
use App\Entity\User;
use App\Security\Voter\ClubVoter;
use App\Service\ClubResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class ClubVoterTest extends TestCase
{
    private function voterWithoutResolvedClub(): ClubVoter
    {
        $resolver = $this->createMock(ClubResolver::class);
        $resolver->method('resolve')->willReturn(null);

        return new ClubVoter($resolver);
    }

    public function testManagerIsGrantedOnExplicitClubSubject(): void
    {
        $user = new User();
        $club = new Club();
        $membership = (new Membership())->setUser($user)->setClub($club)->setIsManager(true);
        $club->addMembership($membership);

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voterWithoutResolvedClub()->vote($token, $club, ['MANAGE']));
    }

    public function testMemberIsDeniedManageOnExplicitClubSubject(): void
    {
        $user = new User();
        $club = new Club();
        $club->addMembership((new Membership())->setUser($user)->setClub($club));

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voterWithoutResolvedClub()->vote($token, $club, ['MANAGE']));
    }
}
