<?php

namespace App\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Contact\ContactInput;
use App\Entity\Contact;

/**
 * One contact in the API, and what you can do with it.
 * API Platform builds the routes, the docs, the JSON and the errors from these attributes.
 * The providers and the processor do the work through ContactService.
 */
#[ApiResource(
    shortName: 'Contact',
    // Shown in the API docs. Without it the docs would show the comment above.
    description: 'A contact and its QR code.',
    // With the /api prefix from config/routes/api_platform.yaml: /api/v1/...
    routePrefix: '/v1',
    operations: [
        new Post(
            uriTemplate: '/contacts',
            description: 'Validates the contact, generates the vCard 3.0 QR code and stores both. First and last name are required, and at least one of email and phone.',
            // The body is a ContactInput (validated), the answer a ContactResource.
            input: ContactInput::class,
            processor: CreateContactProcessor::class,
            // A wrong type, like "birthday": 5, becomes a 422 for that field, not a 400.
            collectDenormalizationErrors: true,
            openapi: new OpenApiOperation(summary: 'Create a contact and its QR code'),
        ),
        new Get(
            uriTemplate: '/contacts/{id}',
            requirements: ['id' => '\d+'],
            provider: ContactProvider::class,
            openapi: new OpenApiOperation(summary: 'Get one contact'),
        ),
        new Get(
            uriTemplate: '/contacts/{id}/qr',
            requirements: ['id' => '\d+'],
            name: 'contact_qr_api',
            provider: ContactFileProvider::class,
            parameters: [
                'download' => new QueryParameter(
                    schema: ['type' => 'boolean', 'default' => false],
                    description: 'true: answer with Content-Disposition: attachment, so the browser saves a file.',
                ),
            ],
            openapi: new OpenApiOperation(
                summary: 'Get the QR code image of a contact',
                description: 'The stored PNG. Add ?download=true to receive it as a file attachment.',
                responses: ['200' => new OpenApiResponse(
                    description: 'The QR code',
                    content: new \ArrayObject(['image/png' => new MediaType(schema: new \ArrayObject(['type' => 'string', 'format' => 'binary']))]),
                )],
            ),
        ),
        new Get(
            uriTemplate: '/contacts/{id}/vcard',
            requirements: ['id' => '\d+'],
            name: 'contact_vcard_api',
            provider: ContactFileProvider::class,
            openapi: new OpenApiOperation(
                summary: 'Get the vCard 3.0 text inside the QR code',
                responses: ['200' => new OpenApiResponse(
                    description: 'The vCard',
                    content: new \ArrayObject(['text/vcard' => new MediaType(schema: new \ArrayObject(['type' => 'string']))]),
                )],
            ),
        ),
    ],
)]
final class ContactResource
{
    public function __construct(
        public readonly int $id,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $email,
        public readonly ?string $phone,
        /** Date only, "1990-12-10". */
        public readonly ?string $birthday,
        public readonly ?string $street,
        public readonly ?string $postalCode,
        public readonly ?string $city,
        public readonly ?string $country,
        public readonly ?string $organization,
        public readonly ?string $jobTitle,
        /** UTC, ISO 8601. */
        public readonly string $createdAt,
        public readonly string $qrCodeUrl,
        public readonly string $vCardUrl,
    ) {
    }

    public static function fromEntity(Contact $c): self
    {
        $id = (int) $c->getId();

        return new self(
            $id, $c->getFirstName(), $c->getLastName(), $c->getEmail(), $c->getPhone(),
            $c->getBirthday()?->format('Y-m-d'),
            $c->getStreet(), $c->getPostalCode(), $c->getCity(), $c->getCountry(), $c->getOrganization(), $c->getJobTitle(),
            $c->getCreatedAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            "/api/v1/contacts/$id/qr",
            "/api/v1/contacts/$id/vcard",
        );
    }
}
