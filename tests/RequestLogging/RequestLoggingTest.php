<?php

namespace App\Tests\RequestLogging;

use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * Request logging, the log search and proxy headers, with a real MariaDB database.
 * The test client runs kernel.terminate too, so the log row is there right away.
 */
final class RequestLoggingTest extends DatabaseTestCase
{
    // Login for the log (the password is in .env.test).
    private const LOGIN = ['PHP_AUTH_USER' => 'reviewer', 'PHP_AUTH_PW' => 'test-password'];

    private mixed $originalTrustedProxies = null;

    protected function setUp(): void
    {
        $this->originalTrustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? '';
        parent::setUp();
        $this->connection()->executeStatement('TRUNCATE TABLE request_logs');
    }

    protected function tearDown(): void
    {
        // Reset, or it would leak into the next test.
        Request::setTrustedProxies([], -1);
        // Put the old value back. Removing it would break the next test.
        $_SERVER['TRUSTED_PROXIES'] = $_ENV['TRUSTED_PROXIES'] = $this->originalTrustedProxies;
        parent::tearDown();
    }

    public function testAWebPageRequestIsLoggedWithEveryField(): void
    {
        $this->client->request('GET', '/contacts?page=1', server: ['HTTP_USER_AGENT' => 'RequestLoggingTest/1.0']);

        $log = $this->singleLog();
        self::assertSame('UI', $log['source']);
        self::assertSame('GET', $log['method']);
        self::assertSame('/contacts', $log['path']);             // without the query string
        self::assertSame(200, (int) $log['status_code']);
        self::assertSame('RequestLoggingTest/1.0', $log['user_agent']);
        self::assertGreaterThan(0, (float) $log['duration_ms']);
        $timestamp = new \DateTimeImmutable($log['timestamp'], new \DateTimeZone('UTC'));
        self::assertEqualsWithDelta(time(), $timestamp->getTimestamp(), 60);
    }

    public function testAnApiRequestIsLoggedAsApiWithItsStatus(): void
    {
        $this->client->jsonRequest('POST', '/api/v1/contacts', ['firstName' => '']);

        $log = $this->singleLog();
        self::assertSame('API', $log['source']);
        self::assertSame('POST', $log['method']);
        self::assertSame(422, (int) $log['status_code']);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function statusAndSource(): iterable
    {
        yield 'web 404' => ['/contacts/999', 'UI', 404];
        yield 'api 404' => ['/api/v1/contacts/999', 'API', 404];
        yield 'health' => ['/health', 'API', 200];
    }

    #[DataProvider('statusAndSource')]
    public function testStatusAndSourceAreRecorded(string $url, string $source, int $status): void
    {
        $this->client->request('GET', $url);

        $log = $this->singleLog();
        self::assertSame($source, $log['source']);
        self::assertSame($status, (int) $log['status_code']);
    }

    public function testForwardedHeadersAreIgnoredFromAnUntrustedAddress(): void
    {
        // TRUSTED_PROXIES is empty: nobody's X-Forwarded-For is believed.
        $this->client->request('GET', '/health/live', server: ['REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9']);

        self::assertSame('198.51.100.7', $this->singleLog()['ip_address']);
    }

    public function testForwardedHeadersAreBelievedFromATrustedProxy(): void
    {
        // A new kernel, so it reads the new TRUSTED_PROXIES.
        self::ensureKernelShutdown();
        $_SERVER['TRUSTED_PROXIES'] = $_ENV['TRUSTED_PROXIES'] = '172.16.0.0/12';
        $client = static::createClient();

        $client->request('GET', '/health/live', server: ['REMOTE_ADDR' => '172.22.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9']);

        self::assertSame('203.0.113.9', $this->singleLog()['ip_address']);
    }

    public function testTheLogApiFiltersBySourceStatusAndPath(): void
    {
        $this->client->request('GET', '/contacts');
        $this->client->request('GET', '/contacts/999');
        $this->client->request('GET', '/api/v1/contacts');

        $notFound = $this->search('?status=404');
        self::assertSame(1, $notFound['totalCount']);
        self::assertSame('/contacts/999', $notFound['items'][0]['path']);

        // Every search is logged too. The filters are chosen so that does not change the counts.
        self::assertSame(1, $this->search('?source=api&path=/api/v1/contacts')['totalCount']);   // case-insensitive
        self::assertSame(2, $this->search('?source=UI')['totalCount']);
        self::assertSame(2, $this->search('?path=CONTACTS&source=UI')['totalCount']);
    }

    public function testTheLogApiFiltersByTime(): void
    {
        $this->client->request('GET', '/contacts');

        $future = gmdate('Y-m-d\TH:i:s', time() + 300);
        $past = gmdate('Y-m-d\TH:i:s', time() - 300);

        self::assertSame(0, $this->search("?from=$future")['totalCount']);
        self::assertSame(1, $this->search("?from=$past&to=$future&source=UI")['totalCount']);
    }

    public function testAnUnknownSourceIsRejected(): void
    {
        $body = $this->search('?source=web');

        // 422 with a violation, like every other validation error.
        self::assertResponseStatusCodeSame(422);
        self::assertSame('source', $body['violations'][0]['propertyPath']);
        self::assertSame('Source is either UI or API.', $body['violations'][0]['message']);
    }

    public function testTheLogPageShowsTheFilteredRequests(): void
    {
        $this->client->request('GET', '/contacts/999');

        $this->client->request('GET', '/logs?status=404', server: self::LOGIN);
        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('1 requests match.', $html);
        self::assertStringContainsString('/contacts/999', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function singleLog(): array
    {
        $rows = $this->connection()->fetchAllAssociative('SELECT * FROM request_logs ORDER BY id');
        self::assertCount(1, $rows);

        return $rows[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function search(string $query): array
    {
        $this->client->request('GET', '/api/v1/request-logs'.$query, server: ['HTTP_ACCEPT' => 'application/json'] + self::LOGIN);

        return json_decode((string) $this->client->getResponse()->getContent(), true);
    }
}
