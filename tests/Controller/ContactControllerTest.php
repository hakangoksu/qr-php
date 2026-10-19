<?php

namespace App\Tests\Controller;

use App\Contact\ContactInput;
use App\Contact\ContactService;
use App\Contact\QrCodeRenderer;
use App\Contact\VCardBuilder;
use App\Entity\Contact;
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The web pages end to end, with a real MariaDB database.
 */
final class ContactControllerTest extends DatabaseTestCase
{
    // A browser sends this header with a form from the same site. Symfony's CSRF check needs it.
    private const SAME_ORIGIN = ['HTTP_SEC_FETCH_SITE' => 'same-origin'];

    public function testTheFormPageRenders(): void
    {
        $crawler = $this->client->request('GET', '/contacts/new');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="contact[firstName]"]'));
        self::assertCount(1, $crawler->filter('input[name="contact[_token]"]'));
    }

    public function testRootRedirectsToTheForm(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/contacts/new');
    }

    public function testAValidFormStoresTheContactAndRedirectsToItsPage(): void
    {
        $this->submit([
            'contact[firstName]' => 'Ada',
            'contact[lastName]' => 'Lovelace',
            'contact[email]' => '  ada@example.com ',
            'contact[birthday]' => '1990-12-10',
            'contact[organization]' => 'Babbage, Lovelace; Partners',
        ]);

        self::assertResponseRedirects('/contacts/1');

        $stored = $this->findContact(1);
        self::assertSame('ada@example.com', $stored->getEmail());   // trimmed
        self::assertNull($stored->getPhone());                     // empty field stored as NULL, not ""
        self::assertSame('1990-12-10', $stored->getBirthday()?->format('Y-m-d'));
        // The saved image contains exactly the saved data.
        $expected = (new QrCodeRenderer())->renderPng((new VCardBuilder())->build($stored));
        self::assertSame($expected, $stored->getQrPng());

        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Ada Lovelace');
        self::assertSelectorTextContains('pre.vcard', 'ORG:Babbage\\, Lovelace\\; Partners');
    }

    public function testMissingNamesReturnTheFormWithErrorsAndStoreNothing(): void
    {
        $this->submit(['contact[email]' => 'ada@example.com']);

        self::assertResponseStatusCodeSame(422);
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Enter a first name.', $html);
        self::assertStringContainsString('Enter a last name.', $html);
        self::assertStringContainsString('value="ada@example.com"', $html);   // the user's input is kept
        self::assertSame(0, $this->countContacts());
    }

    public function testMissingEmailAndPhoneShowsTheCrossFieldError(): void
    {
        $this->submit(['contact[firstName]' => 'Ada', 'contact[lastName]' => 'Lovelace']);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Enter an email address or a phone number, or both.', (string) $this->client->getResponse()->getContent());
        self::assertSame(0, $this->countContacts());
    }

    public function testInvalidFormatsAreReportedPerField(): void
    {
        $this->submit([
            'contact[firstName]' => 'Ada',
            'contact[lastName]' => 'Lovelace',
            'contact[email]' => 'not-an-email',
            'contact[phone]' => '12',
            'contact[birthday]' => 'not-a-date',
        ]);

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Enter an email address like name@example.com.', $html);
        self::assertStringContainsString('Enter a phone number with 7 to 15 digits', $html);
        self::assertStringContainsString('Enter a valid date.', $html);
        self::assertSame(0, $this->countContacts());
    }

    public function testMarkupInTheInputIsShownAsTextNotExecuted(): void
    {
        $this->submit([
            'contact[firstName]' => '<script>alert(1)</script>',
            'contact[lastName]' => 'Lovelace',
            'contact[email]' => 'ada@example.com',
        ]);

        $this->client->request('GET', '/contacts/1');
        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function testACrossSitePostIsRejected(): void
    {
        // What a browser sends when another site posts a form to us.
        $this->submit([
            'contact[firstName]' => 'Ada',
            'contact[lastName]' => 'Lovelace',
            'contact[email]' => 'ada@example.com',
        ], serverParameters: ['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'https://evil.example']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.error-summary', 'The CSRF token is invalid');
        self::assertSame(0, $this->countContacts());
    }

    public function testHistoryListsContactsNewestFirst(): void
    {
        $this->submitValid('Ada', 'Lovelace');
        $this->submitValid('Grace', 'Hopper');

        $this->client->request('GET', '/contacts');
        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('2 contacts', $html);
        self::assertLessThan(strpos($html, 'Ada Lovelace'), strpos($html, 'Grace Hopper'));
    }

    public function testEmptyHistorySaysSo(): void
    {
        $this->client->request('GET', '/contacts');

        self::assertSelectorTextContains('.empty', 'No contacts yet.');
    }

    public function testTheQrImageCanBeShownAndDownloaded(): void
    {
        $this->submitValid('Ada', 'Lovelace');

        $this->client->request('GET', '/contacts/1/qr.png');
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertResponseNotHasHeader('Content-Disposition');

        $this->client->request('GET', '/contacts/1/qr.png?download=true');
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=contact-1-qr.png');
        self::assertSame($this->findContact(1)->getQrPng(), $this->client->getResponse()->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownUrls(): iterable
    {
        yield 'details' => ['/contacts/999'];
        yield 'qr' => ['/contacts/999/qr.png'];
    }

    #[DataProvider('unknownUrls')]
    public function testAnUnknownContactIs404(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheServiceCleansInputThatDidNotComeThroughTheForm(): void
    {
        // The form already trims the input, so test the service directly. The API uses it
        // without a form.
        $input = new ContactInput();
        $input->firstName = '  Ada ';
        $input->lastName = 'Lovelace';
        $input->email = ' ada@example.com ';
        $input->phone = '   ';

        $contact = static::getContainer()->get(ContactService::class)->create($input);

        self::assertSame('Ada', $contact->getFirstName());
        self::assertSame('ada@example.com', $contact->getEmail());
        self::assertNull($contact->getPhone());
    }

    public function testTheDatabaseItselfRejectsAContactWithoutEmailOrPhone(): void
    {
        // Straight to the database, past the form and the service.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist((new Contact('Ada', 'Lovelace'))->setQrPng("\x01"));

        try {
            $entityManager->flush();
            self::fail('The database accepted a contact without email or phone.');
        } catch (DriverException $e) {
            // MariaDB reports a broken CHECK as SQLSTATE 23000 with the constraint's name.
            self::assertSame('23000', $e->getSQLState());
            self::assertStringContainsString('ck_contacts_email_or_phone', $e->getMessage());
        }
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $serverParameters
     */
    private function submit(array $fields, array $serverParameters = self::SAME_ORIGIN): void
    {
        // Like a browser: open the form, then send it.
        $this->client->request('GET', '/contacts/new');
        $this->client->submitForm('Create QR code', $fields, 'POST', $serverParameters);
    }

    private function submitValid(string $firstName, string $lastName): void
    {
        $this->submit([
            'contact[firstName]' => $firstName,
            'contact[lastName]' => $lastName,
            'contact[phone]' => '+370 600 12345',
        ]);
        self::assertResponseRedirects();
    }

    private function countContacts(): int
    {
        return (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM contacts');
    }

    private function findContact(int $id): Contact
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();   // read from the database, not from Doctrine's memory

        return $entityManager->find(Contact::class, $id) ?? throw new \LogicException("Contact $id not found");
    }
}
