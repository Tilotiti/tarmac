<?php

namespace App\Controller\Admin;

use App\Controller\ExtendedController;
use App\Entity\User;
use App\Form\UserEditType;
use App\Repository\Paginator;
use App\Repository\ResetPasswordRequestRepository;
use App\Repository\UserRepository;
use App\Form\Filter\UserFilterType;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/admin/users', host: 'www.%domain%')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'admin_user_index')]
    public function index(Request $request): Response
    {
        $filter = $this->createFilter(UserFilterType::class);
        $filter->handleRequest($request);

        $params = $filter->getData() ?? [];

        $users = Paginator::paginate(
            $this->userRepository->queryByFilters($params),
            $request->query->getInt('page', 1),
            12
        );

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'filters' => $filter->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_user_show')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'admin_dashboard'],
        ['label' => 'Utilisateurs', 'route' => 'admin_user_index'],
        ['label' => '$user.fullname'],
    ])]
    public function show(User $user, Request $request): Response
    {
        $resetLinkFlashes = $request->getSession()->getFlashBag()->get('reset_password_link');

        return $this->render('admin/user/show.html.twig', [
            'user' => $user,
            'resetPasswordLink' => $resetLinkFlashes[0] ?? null,
        ]);
    }

    #[Route('/{id}/reset-password-link', name: 'admin_user_reset_password_link', methods: ['POST'])]
    public function generateResetPasswordLink(
        User $user,
        ResetPasswordHelperInterface $resetPasswordHelper,
        ResetPasswordRequestRepository $resetPasswordRequestRepository,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        $resetPasswordRequestRepository->removeRequests($user);

        try {
            $resetToken = $resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('danger', sprintf('Impossible de générer le lien : %s', $e->getReason()));
            return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
        }

        $url = $urlGenerator->generate(
            'public_reset_password',
            ['token' => $resetToken->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->addFlash('reset_password_link', $url);

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'admin_dashboard'],
        ['label' => 'Utilisateurs', 'route' => 'admin_user_index'],
        ['label' => '$user.fullname', 'route' => 'admin_user_show', 'parameters' => ['id' => '$user.id']],
        ['label' => 'Modifier'],
    ])]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'userUpdated');
            return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
        }

        return $this->render('admin/user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/{id}/disable', name: 'admin_user_disable', methods: ['POST'])]
    public function disable(User $user): Response
    {
        // Prevent self-disabling
        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'cannotDisableSelf');
            return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
        }

        $user->setActive(!$user->isActive());
        $this->entityManager->flush();

        $message = $user->isActive()
            ? 'userEnabled'
            : 'userDisabled';

        $this->addFlash('success', $message);

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', methods: ['POST', 'GET'])]
    public function delete(User $user): Response
    {
        // Prevent self-deletion
        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'cannotDeleteSelf');
            return $this->redirectToRoute('admin_user_index');
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'userDeleted');

        return $this->redirectToRoute('admin_user_index');
    }
}

