<?php

namespace App\Api;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Adds example requests and responses to the OpenAPI docs. The examples are real
 * responses of this API. API Platform makes the rest from the #[ApiResource] attributes.
 *
 * #[AsDecorator]: this class wraps API Platform's own factory ($inner).
 */
#[AsDecorator('api_platform.openapi.factory')]
final class OpenApiExamples implements OpenApiFactoryInterface
{
    private const CREATE_REQUEST = [
        'firstName' => 'Ada',
        'lastName' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+44 20 7946 0000',
        'birthday' => '1990-12-10',
        'organization' => 'Analytical Engines Ltd',
        'jobTitle' => 'Analyst',
    ];

    private const CONTACT = [
        'id' => 1,
        'firstName' => 'Ada',
        'lastName' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+44 20 7946 0000',
        'birthday' => '1990-12-10',
        'street' => null,
        'postalCode' => null,
        'city' => null,
        'country' => null,
        'organization' => 'Analytical Engines Ltd',
        'jobTitle' => 'Analyst',
        'createdAt' => '2026-10-10T05:41:53Z',
        'qrCodeUrl' => '/api/v1/contacts/1/qr',
        'vCardUrl' => '/api/v1/contacts/1/vcard',
    ];

    private const VALIDATION_PROBLEM = [
        'status' => 422,
        'violations' => [
            ['propertyPath' => 'firstName', 'message' => 'Enter a first name.', 'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3'],
            ['propertyPath' => 'email', 'message' => 'Enter an email address like name@example.com.', 'code' => 'bd79c0ab-ddba-46cc-a703-a7a4b08de310'],
        ],
        'detail' => "firstName: Enter a first name.\nemail: Enter an email address like name@example.com.",
        'type' => '/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=bd79c0ab-ddba-46cc-a703-a7a4b08de310',
        'title' => 'An error occurred',
    ];

    private const NOT_FOUND_PROBLEM = [
        'title' => 'Contact not found',
        'detail' => 'There is no contact with id 999.',
        'status' => 404,
        'type' => 'https://tools.ietf.org/html/rfc9110#section-15.5.5',
    ];

    public function __construct(private readonly OpenApiFactoryInterface $inner)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->inner)($context);
        $paths = $openApi->getPaths();

        $contacts = $paths->getPath('/api/v1/contacts');
        if (null !== $contacts && null !== $post = $contacts->getPost()) {
            $post = self::withRequestExample($post, self::CREATE_REQUEST);
            $post = self::withResponseExample($post, '201', 'application/json', self::CONTACT);
            $post = self::withResponseExample($post, '422', 'application/problem+json', self::VALIDATION_PROBLEM);
            $paths->addPath('/api/v1/contacts', $contacts->withPost($post));
        }

        $contact = $paths->getPath('/api/v1/contacts/{id}');
        if (null !== $contact && null !== $get = $contact->getGet()) {
            $get = self::withResponseExample($get, '200', 'application/json', self::CONTACT);
            $get = self::withResponseExample($get, '404', 'application/problem+json', self::NOT_FOUND_PROBLEM);
            $paths->addPath('/api/v1/contacts/{id}', $contact->withGet($get));
        }

        return $openApi;
    }

    /**
     * @param array<string, mixed> $example
     */
    private static function withRequestExample(Operation $operation, array $example): Operation
    {
        $body = $operation->getRequestBody();
        $content = $body?->getContent();
        if (null === $body || null === $content || !isset($content['application/json'])) {
            return $operation;
        }

        $content = clone $content;   // the model is immutable: change a copy, then swap it in
        $content['application/json'] = self::mediaType($content['application/json'])->withExample($example);

        return $operation->withRequestBody($body->withContent($content));
    }

    /**
     * @param array<string, mixed> $example
     */
    private static function withResponseExample(Operation $operation, string $status, string $mediaType, array $example): Operation
    {
        $responses = $operation->getResponses() ?? [];
        $response = $responses[$status] ?? null;
        $content = $response?->getContent();
        if (null === $response || null === $content || !isset($content[$mediaType])) {
            return $operation;
        }

        $content = clone $content;
        $content[$mediaType] = self::mediaType($content[$mediaType])->withExample($example);
        $responses[$status] = $response->withContent($content);

        return $operation->withResponses($responses);
    }

    /**
     * Some media types are MediaType objects, some are plain arrays.
     */
    private static function mediaType(MediaType|array $value): MediaType
    {
        return $value instanceof MediaType ? $value : new MediaType(schema: new \ArrayObject($value['schema'] ?? []));
    }
}
