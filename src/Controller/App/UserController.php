<?php

namespace App\Controller\App;

use App\Controller\ExtendedController;
use App\Entity\User;
use App\Form\UserEditType;
use App\Repository\Paginator;
use App\Repository\UserRepository;
use App\Form\Filter\UserFilterType;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/users', host: 'www.%domain%')]
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

    #[Route('', name: 'app_user_index')]
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

        return $this->render('app/user/index.html.twig', [
            'users' => $users,
            'filters' => $filter->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_user_show')]
    public function show(User $user): Response
    {
        return $this->render('app/user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit')]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'L\'utilisateur a été mis à jour.');

            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        return $this->render('app/user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/{id}/disable', name: 'app_user_disable', methods: ['POST'])]
    public function disable(User $user): Response
    {
        // Prevent self-disabling
        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'Vous ne pouvez pas désactiver votre propre compte.');
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        $user->setActive(!$user->isActive());
        $this->entityManager->flush();

        $message = $user->isActive()
            ? 'L\'utilisateur a été activé.'
            : 'L\'utilisateur a été désactivé.';

        $this->addFlash('success', $message);

        return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_user_delete', methods: ['POST', 'GET'])]
    public function delete(User $user): Response
    {
        // Prevent self-deletion
        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_user_index');
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'L\'utilisateur a été supprimé.');

        return $this->redirectToRoute('app_user_index');
    }
}

