<?php

namespace App\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\RequestLogging\RequestLogQuery;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<RequestLogListResource>
 */
final class RequestLogListProvider implements ProviderInterface
{
    public function __construct(private readonly RequestLogQuery $logs)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): RequestLogListResource
    {
        /** @var Request $request */
        $request = $context['request'];
        $query = $request->query;

        // Bad values were already rejected.
        $result = $this->logs->search([
            'source' => $query->get('source'),
            'status' => $query->has('status') ? $query->getInt('status') : null,
            'method' => $query->get('method'),
            'path' => $query->get('path'),
            'from' => self::utc($query->get('from')),
            'to' => self::utc($query->get('to')),
        ], $query->has('page') ? $query->getInt('page') : 1);

        return new RequestLogListResource($result['items'], $result['page'], $result['pageSize'], $result['totalCount'], $result['totalPages']);
    }

    private static function utc(?string $value): ?\DateTimeImmutable
    {
        return null === $value || '' === $value ? null : new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }
}
