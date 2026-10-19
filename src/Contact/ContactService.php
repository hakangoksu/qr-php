<?php

namespace App\Contact;

use App\Entity\Contact;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates and reads contacts. The form and the API both use it, so they behave the same.
 */
final class ContactService
{
    public const PAGE_SIZE = 20;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly VCardBuilder $vCardBuilder,
        private readonly QrCodeRenderer $qrCodeRenderer,
    ) {
    }

    public function create(ContactInput $input): Contact
    {
        $contact = (new Contact(trim((string) $input->firstName), trim((string) $input->lastName)))
            ->setEmail(self::clean($input->email))
            ->setPhone(self::clean($input->phone))
            ->setBirthday($input->birthday)
            ->setStreet(self::clean($input->street))
            ->setPostalCode(self::clean($input->postalCode))
            ->setCity(self::clean($input->city))
            ->setCountry(self::clean($input->country))
            ->setOrganization(self::clean($input->organization))
            ->setJobTitle(self::clean($input->jobTitle));
        $contact->setQrPng($this->qrCodeRenderer->renderPng($this->vCardBuilder->build($contact)));

        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        return $contact;
    }

    /**
     * One page of contacts, newest first. Plain SQL: the list needs only a few columns,
     * and loading full entities would also load every QR image.
     *
     * @return array{items: list<array{id: int, first_name: string, last_name: string, email: ?string, phone: ?string, created_at: string}>, page: int, pageSize: int, totalCount: int, totalPages: int}
     */
    public function list(int $page, ?string $search = null): array
    {
        $page = max(1, $page);

        // Search in name, email, phone and organization. MariaDB ignores case here.
        // % and _ in the search text are escaped, so they are not wildcards.
        $where = '';
        $params = ['limit' => self::PAGE_SIZE, 'offset' => ($page - 1) * self::PAGE_SIZE];
        if (null !== $search && '' !== trim($search)) {
            $where = 'WHERE first_name LIKE :q OR last_name LIKE :q OR email LIKE :q OR phone LIKE :q OR organization LIKE :q';
            $params['q'] = '%'.addcslashes(trim($search), '\\%_').'%';
        }

        $total = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM contacts $where", array_diff_key($params, ['limit' => 0, 'offset' => 0]));
        $items = $this->connection->fetchAllAssociative(
            "SELECT id, first_name, last_name, email, phone, created_at FROM contacts $where
             ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset",
            $params,
            ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
        );

        return [
            'items' => $items,
            'page' => $page,
            'pageSize' => self::PAGE_SIZE,
            'totalCount' => $total,
            'totalPages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
        ];
    }

    public function find(int $id): ?Contact
    {
        return $this->entityManager->find(Contact::class, $id);
    }

    /**
     * Only the image column, for the QR download.
     */
    public function findQrPng(int $id): ?string
    {
        $png = $this->connection->fetchOne('SELECT qr_png FROM contacts WHERE id = :id', ['id' => $id]);

        return false === $png ? null : $png;
    }

    /**
     * Empty input is stored as NULL, not as "". The CHECK (email or phone) needs that.
     */
    private static function clean(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : trim($value);
    }
}
