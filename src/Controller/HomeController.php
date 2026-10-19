<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * "/" opens the form.
 */
final class HomeController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function __invoke(UrlGeneratorInterface $urls): RedirectResponse
    {
        return new RedirectResponse($urls->generate('contact_new'));
    }
}
