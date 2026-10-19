<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(Connection $connection): Response
    {
        try {
            // The cheapest query that still needs a working connection and login.
            $connection->executeQuery('SELECT 1');
        } catch (DbalException) {
            return self::plain('Unhealthy', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return self::plain('Healthy', Response::HTTP_OK);
    }

    private static function plain(string $body, int $status): Response
    {
        return new Response($body, $status, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-store',
        ]);
    }
}
