<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


final class HealthController
{
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function ready(Connection $connection): JsonResponse
    {
        $started = hrtime(true);
        try {
            // MariaDB stops the query after 3 seconds. The connect limit is in doctrine.yaml.
            $connection->executeQuery('SET STATEMENT max_statement_time=3 FOR SELECT 1');
            $status = 'Healthy';
        } catch (DbalException) {
            $status = 'Unhealthy';
        }
        $durationMs = round((hrtime(true) - $started) / 1e6, 1);

        return self::respond($status, $durationMs, [['name' => 'database', 'status' => $status, 'durationMs' => $durationMs]]);
    }

    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return self::respond('Healthy', 0.0, []);
    }

    /**
     * @param list<array{name: string, status: string, durationMs: float}> $checks
     */
    private static function respond(string $status, float $durationMs, array $checks): JsonResponse
    {
        return new JsonResponse(
            ['status' => $status, 'totalDurationMs' => $durationMs, 'checks' => $checks],
            'Healthy' === $status ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
            ['Cache-Control' => 'no-store'],
        );
    }
}
