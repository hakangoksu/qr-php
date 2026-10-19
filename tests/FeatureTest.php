<?php

namespace App\Tests;

use App\RequestLogging\RequestLogRetention;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Tester\CommandTester;

final class FeatureTest extends DatabaseTestCase
{
    /** @var array<string, mixed> */
    private array $originalEnv = [];

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $name => $value) {
            $_SERVER[$name] = $_ENV[$name] = $value;
        }
        parent::tearDown();
    }

    /**
     * A new kernel with some settings changed. tearDown puts them back.
     */
    private function clientWith(array $env): KernelBrowser
    {
        self::ensureKernelShutdown();
        foreach ($env as $name => $value) {
            $this->originalEnv[$name] ??= $_SERVER[$name] ?? '';
            $_SERVER[$name] = $_ENV[$name] = $value;
        }

        return static::createClient();
    }

    // --- Log access ------------------------------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function logUrls(): iterable
    {
        yield 'page' => ['/logs'];
        yield 'api' => ['/api/v1/request-logs'];
    }

    #[DataProvider('logUrls')]
    public function testWithoutAConfiguredPasswordTheLogIsClosed(string $url): void
    {
        $client = $this->clientWith(['LOG_VIEWER_PASSWORD' => '']);

        $client->request('GET', $url);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function missingOrWrongPassword(): iterable
    {
        yield 'page, no password' => ['/logs', null];
        yield 'api, no password' => ['/api/v1/request-logs', null];
        // The router decodes the path: these are /logs and /api/v1/request-logs.
        yield 'page, percent-encoded' => ['/%6cogs', null];
        yield 'api, percent-encoded' => ['/api/v1/request%2dlogs', null];
        yield 'wrong password' => ['/logs', 'wrong'];
    }

    #[DataProvider('missingOrWrongPassword')]
    public function testAMissingOrWrongPasswordGets401WithALoginPrompt(string $url, ?string $password): void
    {
        $server = null === $password ? [] : ['PHP_AUTH_USER' => 'reviewer', 'PHP_AUTH_PW' => $password];

        $this->client->request('GET', $url, server: $server);

        self::assertResponseStatusCodeSame(401);
        self::assertStringStartsWith('Basic', (string) $this->client->getResponse()->headers->get('WWW-Authenticate'));
    }

    /**
     * Spellings that are no route at all end as 404, without showing anything.
     */
    #[DataProvider('notALogRoute')]
    public function testOtherSpellingsAreNotFound(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notALogRoute(): iterable
    {
        yield 'upper case' => ['/LOGS'];
        yield 'format suffix' => ['/api/v1/request-logs.json'];
    }

    public function testTheRightPasswordOpensTheLog(): void
    {
        $login = ['PHP_AUTH_USER' => 'reviewer', 'PHP_AUTH_PW' => 'test-password'];

        $this->client->request('GET', '/logs', server: $login);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/v1/request-logs', server: $login + ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
    }

    public function testTheRestOfTheApplicationNeedsNoPassword(): void
    {
        $this->client->request('GET', '/contacts');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/v1/contacts', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
    }

    // --- Rate limiting -----------------------------------------------------------------

    public function testCreatingContactsIsLimitedPerClient(): void
    {
        $client = $this->clientWith(['RATE_LIMIT_CONTACTS_PER_MINUTE' => '2']);
        // A new address every time: the counters outlive a test.
        $from = ['REMOTE_ADDR' => sprintf('198.51.100.%d', random_int(1, 254))];

        for ($i = 0; $i < 2; ++$i) {
            $client->jsonRequest('POST', '/api/v1/contacts', ['firstName' => "Ada$i", 'lastName' => 'L', 'phone' => '+370 600 12345'], $from);
            self::assertResponseStatusCodeSame(201);
        }
        // The same route, spelled differently: still the same counter.
        $client->jsonRequest('POST', '/api/v1/%63ontacts', ['firstName' => 'Ada3', 'lastName' => 'L', 'phone' => '+370 600 12345'], $from);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertGreaterThan(0, (int) $client->getResponse()->headers->get('Retry-After'));
        // Reading is not limited.
        $client->request('GET', '/api/v1/contacts', server: $from + ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $client->request('GET', '/contacts/new', server: $from);
        self::assertResponseIsSuccessful();
    }

    // --- Search ------------------------------------------------------------------------

    public function testContactsCanBeSearchedInTheApiAndTheHistory(): void
    {
        $this->create('Ada', 'Lovelace', 'ada@example.com');
        $this->create('Grace', 'Hopper', 'grace@navy.example');

        $api = $this->getJson('/api/v1/contacts?q=HOPPER');
        self::assertSame(1, $api['totalCount']);
        self::assertSame('Grace', $api['items'][0]['firstName']);

        self::assertSame(2, $this->getJson('/api/v1/contacts?q=example')['totalCount']);

        $this->client->request('GET', '/contacts?q=ada');
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Ada Lovelace', $html);
        self::assertStringNotContainsString('Grace Hopper', $html);
    }

    public function testSearchWildcardsAreTakenLiterally(): void
    {
        $this->create('Ada', 'Lovelace', 'ada@example.com');

        self::assertSame(0, $this->getJson('/api/v1/contacts?q=%25')['totalCount']);   // q=%
    }

    // --- Retention ---------------------------------------------------------------------

    public function testRetentionDeletesOnlyRowsOlderThanTheLimit(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('TRUNCATE TABLE request_logs');
        foreach (['/old' => '-100 days', '/recent' => '-1 day'] as $path => $age) {
            $connection->insert('request_logs', [
                'timestamp' => (new \DateTimeImmutable($age, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
                'method' => 'GET', 'path' => $path, 'status_code' => 200, 'source' => 'UI', 'duration_ms' => 1,
            ]);
        }

        $deleted = static::getContainer()->get(RequestLogRetention::class)->prune(90);

        self::assertSame(1, $deleted);
        self::assertSame(['/recent'], $connection->fetchFirstColumn('SELECT path FROM request_logs'));
    }

    public function testThePruneCommandRuns(): void
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:request-log:prune'));

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Deleted', $tester->getDisplay());
    }

    private function create(string $firstName, string $lastName, string $email): void
    {
        $this->client->jsonRequest('POST', '/api/v1/contacts', ['firstName' => $firstName, 'lastName' => $lastName, 'email' => $email]);
        self::assertResponseStatusCodeSame(201);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $url): array
    {
        $this->client->request('GET', $url, server: ['HTTP_ACCEPT' => 'application/json']);

        return json_decode((string) $this->client->getResponse()->getContent(), true);
    }
}
