<?php

namespace App\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Contact\ContactService;

/**
 * GET /api/v1/contacts/{id}.
 *
 * @implements ProviderInterface<ContactResource>
 */
final class ContactProvider implements ProviderInterface
{
    public function __construct(private readonly ContactService $contacts)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ContactResource
    {
        $contact = $this->contacts->find((int) $uriVariables['id'])
            ?? throw new ContactNotFound((int) $uriVariables['id']);

        return ContactResource::fromEntity($contact);
    }
}
