<?php

namespace App\Tests\Api;

use App\Contact\VCardBuilder;
use App\Entity\Contact;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The REST API end to end, with a real MariaDB database.
 */
final class ContactApiTest extends DatabaseTestCase
{
    private const CONTACTS = '/api/v1/contacts';

    public function testCreateReturns201WithLocationAndTheStoredContact(): void
    {
        $body = $this->postJson([
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'email' => ' ada@example.com ',
            'birthday' => '1990-12-10',
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Location', '/api/v1/contacts/1');
        self::assertSame(1, $body['id']);
        self::assertSame('ada@example.com', $body['email']);   // trimmed
        self::assertArrayHasKey('phone', $body);                // null fields are written, not left out
        self::assertNull($body['phone']);
        self::assertSame('1990-12-10', $body['birthday']);
        self::assertSame('/api/v1/contacts/1/qr', $body['qrCodeUrl']);
        self::assertArrayNotHasKey('qrPng', $body);             // the image is not in the JSON
    }

    public function testInvalidInputReturns422ProblemDetailsWithOneViolationPerField(): void
    {
        // API Platform answers 422 with a "violations" list.
        $body = $this->postJson(['firstName' => '', 'email' => 'nope', 'phone' => '12']);

        self::assertResponseStatusCodeSame(422);
        self::assertProblemJson();
        $messages = self::messagesByField($body);
        self::assertSame(['Enter a first name.'], $messages['firstName']);
        self::assertSame(['Enter a last name.'], $messages['lastName']);
        self::assertArrayHasKey('email', $messages);
        self::assertArrayHasKey('phone', $messages);
        self::assertSame(0, $this->countContacts());
    }

    public function testTheCrossFieldRuleAppliesToTheApiToo(): void
    {
        $body = $this->postJson(['firstName' => 'Ada', 'lastName' => 'Lovelace']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Enter an email address or a phone number, or both.'], self::messagesByField($body)['email']);
    }

    public function testAWrongTypeIsReportedForItsField(): void
    {
        $body = $this->postJson(['firstName' => 'Ada', 'lastName' => 'Lovelace', 'email' => 'a@example.com', 'birthday' => 'not-a-date']);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('birthday', self::messagesByField($body));
    }

    public function testUnreadableJsonReturns400ProblemDetails(): void
    {
        $this->client->request('POST', self::CONTACTS, server: ['CONTENT_TYPE' => 'application/json'], content: '{"firstName":');

        self::assertResponseStatusCodeSame(400);
        self::assertProblemJson();
    }

    public function testABodyThatIsNotJsonReturns415(): void
    {
        $this->client->request('POST', self::CONTACTS, server: ['CONTENT_TYPE' => 'text/plain'], content: 'hello');

        self::assertResponseStatusCodeSame(415);
    }

    public function testListReturnsAPageNewestFirst(): void
    {
        $this->create('Ada', 'Lovelace');
        $this->create('Grace', 'Hopper');

        $body = $this->getJson(self::CONTACTS);

        self::assertSame(2, $body['totalCount']);
        self::assertSame(1, $body['page']);
        self::assertSame('Grace', $body['items'][0]['firstName']);
        self::assertSame('Ada', $body['items'][1]['firstName']);
    }

    public function testAnInvalidPageNumberIsReported(): void
    {
        $body = $this->getJson(self::CONTACTS.'?page=abc');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Page must be a whole number, 1 or more.'], self::messagesByField($body)['page']);
    }

    public function testGetReturnsOneContact(): void
    {
        $this->create('Ada', 'Lovelace');

        self::assertSame('Lovelace', $this->getJson(self::CONTACTS.'/1')['lastName']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownContactUrls(): iterable
    {
        yield 'contact' => ['/api/v1/contacts/999'];
        yield 'qr' => ['/api/v1/contacts/999/qr'];
        yield 'vcard' => ['/api/v1/contacts/999/vcard'];
    }

    #[DataProvider('unknownContactUrls')]
    public function testAnUnknownContactIs404ProblemDetails(string $url): void
    {
        $body = $this->getJson($url);

        self::assertResponseStatusCodeSame(404);
        self::assertProblemJson();
        self::assertSame('Contact not found', $body['title']);
        self::assertSame(404, $body['status']);
    }

    public function testAnUnknownApiUrlIs404ProblemDetails(): void
    {
        $this->client->request('GET', '/api/v1/nothing-here');

        self::assertResponseStatusCodeSame(404);
        self::assertProblemJson();
    }

    public function testTheQrCodeAndTheVcardCanBeFetched(): void
    {
        $this->create('Ada', 'Lovelace');
        $stored = $this->findContact(1);

        $this->client->request('GET', self::CONTACTS.'/1/qr');
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertSame($stored->getQrPng(), $this->client->getResponse()->getContent());

        $this->client->request('GET', self::CONTACTS.'/1/qr?download=true');
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=contact-1-qr.png');

        $this->client->request('GET', self::CONTACTS.'/1/vcard');
        self::assertResponseHeaderSame('Content-Type', 'text/vcard; charset=utf-8');
        self::assertSame((new VCardBuilder())->build($stored), $this->client->getResponse()->getContent());
    }

    public function testAContactCreatedThroughTheApiShowsUpInTheWebHistory(): void
    {
        $this->create('Ada', 'Lovelace');

        $this->client->request('GET', '/contacts');

        self::assertStringContainsString('Ada Lovelace', (string) $this->client->getResponse()->getContent());
    }

    public function testTheOpenapiDocumentDescribesEveryEndpointWithExamples(): void
    {
        $this->client->request('GET', '/api/docs', server: ['HTTP_ACCEPT' => 'application/vnd.openapi+json']);
        $document = json_decode((string) $this->client->getResponse()->getContent(), true);

        $paths = $document['paths'];
        foreach (['/api/v1/contacts', '/api/v1/contacts/{id}', '/api/v1/contacts/{id}/qr', '/api/v1/contacts/{id}/vcard'] as $path) {
            self::assertArrayHasKey($path, $paths);
        }
        self::assertArrayHasKey('example', $paths['/api/v1/contacts']['post']['requestBody']['content']['application/json']);
        self::assertArrayHasKey('422', $paths['/api/v1/contacts']['post']['responses']);
    }

    public function testTheInteractiveDocumentationIsServed(): void
    {
        $this->client->request('GET', '/api/docs', server: ['HTTP_ACCEPT' => 'text/html']);

        self::assertResponseIsSuccessful();
        self::assertStringContainsStringIgnoringCase('swagger', (string) $this->client->getResponse()->getContent());
    }

    private function assertProblemJson(): void
    {
        // Only the media type: some responses add "; charset=utf-8".
        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        self::assertSame('application/problem+json', trim(explode(';', $contentType)[0]), "Content-Type was \"$contentType\"");
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function postJson(array $body): array
    {
        $this->client->jsonRequest('POST', self::CONTACTS, $body);

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $url): array
    {
        $this->client->request('GET', $url, server: ['HTTP_ACCEPT' => 'application/json']);

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    private function create(string $firstName, string $lastName): void
    {
        $this->postJson(['firstName' => $firstName, 'lastName' => $lastName, 'phone' => '+370 600 12345']);
        self::assertResponseStatusCodeSame(201);
    }

    /**
     * The violations list, grouped by field.
     *
     * @param array<string, mixed> $problem
     *
     * @return array<string, list<string>>
     */
    private static function messagesByField(array $problem): array
    {
        $byField = [];
        foreach ($problem['violations'] ?? [] as $violation) {
            $byField[$violation['propertyPath']][] = $violation['message'];
        }

        return $byField;
    }

    private function countContacts(): int
    {
        return (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM contacts');
    }

    private function findContact(int $id): Contact
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->find(Contact::class, $id) ?? throw new \LogicException("Contact $id not found");
    }
}
