<?php

namespace App\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Contact\ContactInput;
use App\Contact\ContactService;

/**
 * POST /api/v1/contacts. The body is already validated when it gets here.
 *
 * @implements ProcessorInterface<ContactInput, ContactResource>
 */
final class CreateContactProcessor implements ProcessorInterface
{
    public function __construct(private readonly ContactService $contacts)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ContactResource
    {
        \assert($data instanceof ContactInput);

        // API Platform answers 201 with this object and a Location header.
        return ContactResource::fromEntity($this->contacts->create($data));
    }
}
