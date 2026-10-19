<?php

namespace App\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Contact\ContactService;
use App\Contact\VCardBuilder;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/v1/contacts/{id}/qr and /vcard: a PNG and a text file, not JSON.
 * API Platform sends a returned Response as it is, so this is how it serves a file.
 *
 * @implements ProviderInterface<Response>
 */
final class ContactFileProvider implements ProviderInterface
{
    public function __construct(
        private readonly ContactService $contacts,
        private readonly VCardBuilder $vCardBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $id = (int) $uriVariables['id'];

        if ('contact_vcard_api' === $operation->getName()) {
            $contact = $this->contacts->find($id) ?? throw new ContactNotFound($id);

            return new Response($this->vCardBuilder->build($contact), Response::HTTP_OK, ['Content-Type' => 'text/vcard; charset=utf-8']);
        }

        $png = $this->contacts->findQrPng($id) ?? throw new ContactNotFound($id);
        $response = new Response($png, Response::HTTP_OK, ['Content-Type' => 'image/png']);
        if (($context['request'] ?? null)?->query->getBoolean('download')) {
            $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                sprintf('contact-%d-qr.png', $id),
            ));
        }

        return $response;
    }
}
