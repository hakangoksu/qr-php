<?php

namespace App\RequestLogging;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Deletes request log rows older than REQUEST_LOG_RETENTION_DAYS (default 90, 0 turns it off).
 * IP addresses are personal data, so they are not kept forever.
 *
 * There is no timer in PHP here. After a request, RequestLogger calls maybePrune(), which
 * cleans up now and then, like PHP's session cleanup. app:request-log:prune does it by hand.
 */
final class RequestLogRetention
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%env(int:REQUEST_LOG_RETENTION_DAYS)%')]
        private readonly int $retentionDays,
        // About 1 in 200 requests. Cheap: usually there is nothing to delete.
        private readonly int $probabilityDivisor = 200,
    ) {
    }

    public function maybePrune(): void
    {
        if ($this->retentionDays > 0 && 1 === random_int(1, $this->probabilityDivisor)) {
            $this->prune();
        }
    }

    public function prune(?int $olderThanDays = null): int
    {
        $days = $olderThanDays ?? $this->retentionDays;
        $cutoff = (new \DateTimeImmutable("-$days days", new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        return (int) $this->connection->executeStatement('DELETE FROM request_logs WHERE timestamp < :cutoff', ['cutoff' => $cutoff]);
    }
}
