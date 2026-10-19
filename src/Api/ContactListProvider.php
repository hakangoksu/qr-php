<?php

namespace App\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Contact\ContactService;

/**
 * GET /api/v1/contacts?page=1.
 *
 * @implements ProviderInterface<ContactListResource>
 */
final class ContactListProvider implements ProviderInterface
{
    public function __construct(private readonly ContactService $contacts)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ContactListResource
    {
        $request = $context['request'] ?? null;
        $page = $request?->query->getInt('page', 1) ?? 1;

        $result = $this->contacts->list($page, $request?->query->getString('q'));

        // Database column names (first_name) to API names (firstName).
        $items = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'firstName' => $row['first_name'],
            'lastName' => $row['last_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'createdAt' => (new \DateTimeImmutable($row['created_at'], new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
        ], $result['items']);

        return new ContactListResource($items, $result['page'], $result['pageSize'], $result['totalCount'], $result['totalPages']);
    }
}
