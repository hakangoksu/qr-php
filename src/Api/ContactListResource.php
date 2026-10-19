<?php

namespace App\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One page of contacts: {"items": [...], "page": 1, "pageSize": 20, "totalCount": 2, "totalPages": 1}.
 * A Get, not a GetCollection: in plain JSON GetCollection leaves the paging fields out.
 */
#[ApiResource(
    shortName: 'ContactList',
    description: 'One page of contacts, newest first.',
    routePrefix: '/v1',
    operations: [
        new Get(
            uriTemplate: '/contacts',
            uriVariables: [],
            provider: ContactListProvider::class,
            parameters: [
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    description: 'Page number, 20 contacts per page.',
                    // Checked before the provider runs: "abc" gives a 422 with this message.
                    constraints: [new Assert\Regex('/^[1-9]\d*$/', message: 'Page must be a whole number, 1 or more.')],
                ),
            ],
            openapi: new OpenApiOperation(
                summary: 'List contacts, newest first',
                description: 'Pages of 20. Each item is a summary without the QR image.',
            ),
        ),
    ],
)]
final class ContactListResource
{
    /**
     * @param list<array{id: int, firstName: string, lastName: string, email: ?string, phone: ?string, createdAt: string}> $items
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
