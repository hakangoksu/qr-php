<?php

namespace App\Api;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 404 for the API. API Platform turns it into problem details with these fields.
 */
final class ContactNotFound extends NotFoundHttpException implements ProblemExceptionInterface
{
    public function __construct(private readonly int $id)
    {
        parent::__construct($this->getDetail());
    }

    public function getType(): string
    {
        return 'https://tools.ietf.org/html/rfc9110#section-15.5.5';
    }

    public function getTitle(): string
    {
        return 'Contact not found';
    }

    public function getStatus(): int
    {
        return 404;
    }

    public function getDetail(): string
    {
        return sprintf('There is no contact with id %d.', $this->id);
    }

    public function getInstance(): ?string
    {
        return null;
    }
}
