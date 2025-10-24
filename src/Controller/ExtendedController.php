<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\SubdomainService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ExtendedController.
 *
 * @method User getUser()
 */
abstract class ExtendedController extends AbstractController
{
    public function __construct(
        protected SubdomainService $subdomainService
    ) {
    }
    /**
     * Redirects to a route with automatic subdomain injection for club routes.
     */
    protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
    {
        // Auto-inject subdomain for club routes
        $parameters = $this->injectSubdomainIfNeeded($route, $parameters);

        return parent::redirectToRoute($route, $parameters, $status);
    }

    /**
     * Injects subdomain parameter for club routes if not already present.
     */
    private function injectSubdomainIfNeeded(string $routeName, array $parameters): array
    {
        // Only inject for club routes
        if (str_starts_with($routeName, 'club_') && !isset($parameters['subdomain'])) {
            $subdomain = $this->subdomainService->getClubSubdomain();
            if ($subdomain) {
                $parameters['subdomain'] = $subdomain;
            }
        }

        return $parameters;
    }

    /**
     * Creates and returns a Form instance from the filter.
     */
    protected function createFilter(string $type, ?array $defaults = null, array $options = []): FormInterface
    {
        $data = [];

        // Store default values in form options for template access
        if ($defaults !== null) {
            $options['defaults'] = $defaults;

            // Automatically handle default filter logic
            if (!empty($defaults)) {
                $request = $this->container->get('request_stack')->getCurrentRequest();

                foreach ($defaults as $key => $defaultValue) {
                    // Check if user explicitly cleared this filter
                    // Use all() to safely get array values without throwing exceptions
                    $queryParams = $request->query->all();
                    $cleared = array_key_exists($key, $queryParams) && $queryParams[$key] === '';

                    // Only apply default if user hasn't explicitly cleared it
                    if (!$cleared) {
                        $data[$key] = $defaultValue;
                    }
                }
            }
        }

        return $this->container->get('form.factory')->createNamed('', $type, $data, $options);
    }

    /**
     * Extracts and cleans filter data from a form, removing null and empty values.
     */
    protected function getFilterData(FormInterface $form): array
    {
        $filters = $form->getData();
        return array_filter($filters, fn($value) => $value !== null && $value !== '');
    }

    protected function back(): RedirectResponse
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $router = $this->container->get('router');

        $back = $request->server->get('HTTP_REFERER', $router->generate('home'));

        return $this->redirect($back);
    }

    protected function reload(): Response
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $router = $this->container->get('router');

        $current = $request->server->get('REQUEST_URI', $router->generate('home'));

        return $this->redirect($current);
    }
}





