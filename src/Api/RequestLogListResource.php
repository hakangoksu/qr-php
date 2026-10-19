<?php

namespace App\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * GET /api/v1/request-logs: one page of the request log. Every filter is checked first.
 */
#[ApiResource(
    shortName: 'RequestLogList',
    description: 'Every HTTP request the application handled, web pages and API alike.',
    routePrefix: '/v1',
    operations: [
        new Get(
            uriTemplate: '/request-logs',
            uriVariables: [],
            provider: RequestLogListProvider::class,
            parameters: [
                'source' => new QueryParameter(
                    schema: ['type' => 'string', 'enum' => ['UI', 'API']],
                    description: 'UI or API (case-insensitive).',
                    constraints: [new Assert\Regex('/^(ui|api)$/i', message: 'Source is either UI or API.')],
                ),
                'status' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 100, 'maximum' => 599],
                    description: 'HTTP status code, e.g. 404.',
                    constraints: [new Assert\Regex('/^[1-5]\d\d$/', message: 'Status is a three-digit HTTP status code.')],
                ),
                'method' => new QueryParameter(schema: ['type' => 'string'], description: 'HTTP method, e.g. POST.'),
                'path' => new QueryParameter(schema: ['type' => 'string'], description: 'Part of the path, e.g. /api/v1/contacts.'),
                'from' => new QueryParameter(
                    schema: ['type' => 'string', 'format' => 'date-time'],
                    description: 'From this time (UTC), inclusive, e.g. 2026-10-10T06:00:00.',
                    constraints: [new Assert\Regex(self::DATE_TIME, message: 'Use a date and time like 2026-10-10T06:00:00.')],
                ),
                'to' => new QueryParameter(
                    schema: ['type' => 'string', 'format' => 'date-time'],
                    description: 'Until this time (UTC), exclusive.',
                    constraints: [new Assert\Regex(self::DATE_TIME, message: 'Use a date and time like 2026-10-10T07:00:00.')],
                ),
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    description: 'Page number, 50 entries per page.',
                    constraints: [new Assert\Regex('/^[1-9]\d*$/', message: 'Page must be a whole number, 1 or more.')],
                ),
            ],
            openapi: new OpenApiOperation(
                summary: 'Search the request log, newest first',
                description: 'All filters are optional and combine with AND. Pages of 50.',
            ),
        ),
    ],
)]
final class RequestLogListResource
{
    // "2026-10-10", "2026-10-10T06:00" or "2026-10-10T06:00:00", in UTC.
    public const DATE_TIME = '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?)?$/';

    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $pageSize,
        public readonly int $totalCount,
        public readonly int $totalPages,
    ) {
    }
}
