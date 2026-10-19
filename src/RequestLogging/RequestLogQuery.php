<?php

namespace App\RequestLogging;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Searches the request log, for the log page and the log API. Every filter is optional.
 */
final class RequestLogQuery
{
    public const PAGE_SIZE = 50;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array{source?: ?string, status?: ?int, method?: ?string, path?: ?string, from?: ?\DateTimeImmutable, to?: ?\DateTimeImmutable} $filter
     *
     * @return array{items: list<array{id: int, timestamp: string, ipAddress: ?string, method: string, path: string, statusCode: int, userAgent: ?string, source: string, durationMs: float}>, page: int, pageSize: int, totalCount: int, totalPages: int}
     */
    public function search(array $filter, int $page): array
    {
        $page = max(1, $page);

        // One condition per filter. Values are parameters, never pasted into the SQL.
        $query = $this->connection->createQueryBuilder()->from('request_logs');

        if (!empty($filter['source'])) {
            $query->andWhere('source = :source')->setParameter('source', strtoupper($filter['source']));
        }
        if (null !== ($filter['status'] ?? null)) {
            $query->andWhere('status_code = :status')->setParameter('status', $filter['status'], ParameterType::INTEGER);
        }
        if (!empty($filter['method'])) {
            $query->andWhere('method = :method')->setParameter('method', strtoupper($filter['method']));
        }
        if (!empty($filter['path'])) {
            // LIKE ignores case in MariaDB. "\" escapes the wildcards.
            $query->andWhere('path LIKE :path')->setParameter('path', '%'.addcslashes($filter['path'], '\\%_').'%');
        }
        if (null !== ($filter['from'] ?? null)) {
            $query->andWhere('timestamp >= :from')->setParameter('from', $filter['from']->format('Y-m-d H:i:s.u'));
        }
        if (null !== ($filter['to'] ?? null)) {
            $query->andWhere('timestamp < :to')->setParameter('to', $filter['to']->format('Y-m-d H:i:s.u'));
        }

        $total = (int) (clone $query)->select('COUNT(*)')->executeQuery()->fetchOne();
        $rows = $query
            ->select('id', 'timestamp', 'ip_address', 'method', 'path', 'status_code', 'user_agent', 'source', 'duration_ms')
            ->orderBy('timestamp', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->executeQuery()
            ->fetchAllAssociative();

        $items = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            // Stored in UTC.
            'timestamp' => (new \DateTimeImmutable($row['timestamp'], new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
            'ipAddress' => $row['ip_address'],
            'method' => $row['method'],
            'path' => $row['path'],
            'statusCode' => (int) $row['status_code'],
            'userAgent' => $row['user_agent'],
            'source' => $row['source'],
            'durationMs' => round((float) $row['duration_ms'], 2),
        ], $rows);

        return [
            'items' => $items,
            'page' => $page,
            'pageSize' => self::PAGE_SIZE,
            'totalCount' => $total,
            'totalPages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
        ];
    }
}
