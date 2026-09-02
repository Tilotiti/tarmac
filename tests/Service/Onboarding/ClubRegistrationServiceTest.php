<?php

namespace App\Tests\Service\Onboarding;

use App\Entity\Club;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Onboarding\ClubRegistrationService;
use App\Service\Onboarding\DuplicateReport;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ClubRegistrationServiceTest extends TestCase
{
    public function testRegisterCreatesManagerMembershipAndSendsTwoEmails(): void
    {
        $admin = (new User())->setEmail('admin@example.test')->setRoles(['ROLE_ADMIN']);
        $president = (new User())->setEmail('president@example.test')->setFirstname('Jean')->setLastname('Dupont');
        $club = (new Club())->setName('Planeurs de Beauce')->setSubdomain('beauce');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($club);
        $entityManager->expects($this->once())->method('flush');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findAdmins')->willReturn([$admin]);

        $sent = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->exactly(2))->method('send')->willReturnCallback(static function (TemplatedEmail $email) use (&$sent): void {
            $sent[] = $email;
        });

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://www.tarmac.test/admin/clubs/1');

        $service = new ClubRegistrationService(
            $entityManager,
            $userRepository,
            $mailer,
            new SubdomainService(new RequestStack(), 'tarmac.test'),
            $urlGenerator,
        );

        $service->register($president, $club, new DuplicateReport());

        $this->assertSame($president, $club->getCreatedBy());
        $this->assertTrue($club->isActive());
        $membership = $club->getMembership($president);
        $this->assertNotNull($membership);
        $this->assertTrue($membership->isManager());

        $this->assertSame('admin@example.test', $sent[0]->getTo()[0]->getAddress());
        $this->assertSame('email/clubCreated.html.twig', $sent[0]->getHtmlTemplate());
        $this->assertSame('president@example.test', $sent[1]->getTo()[0]->getAddress());
        $this->assertSame('email/welcomePresident.html.twig', $sent[1]->getHtmlTemplate());
        $this->assertSame('https://beauce.tarmac.test/', $sent[1]->getContext()['clubUrl']);
    }
}
