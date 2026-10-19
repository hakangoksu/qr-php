<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    private ?string $originalDatabaseUrl = null;

    protected function setUp(): void
    {
        // Nothing listens on port 1, so the database check fails at once.
        $this->originalDatabaseUrl = $_SERVER['DATABASE_URL'] ?? null;
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'mysql://none:none@127.0.0.1:1/none';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $this->originalDatabaseUrl;
    }

    public function testHealthReturns503WhenDatabaseIsUnreachable(): void
    {
        $client = static::createClient();

        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(503);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Unhealthy', $body['status']);
        self::assertSame('database', $body['checks'][0]['name']);
        self::assertSame('Unhealthy', $body['checks'][0]['status']);
        // No exception text in a public response.
        self::assertSame(['name', 'status', 'durationMs'], array_keys($body['checks'][0]));
    }

    public function testLivenessDoesNotDependOnTheDatabase(): void
    {
        $client = static::createClient();

        $client->request('GET', '/health/live');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Healthy', $body['status']);
        self::assertSame([], $body['checks']);
    }

    public function testADatabaseThatNeverAnswersFailsTheCheckWithinTheTimeout(): void
    {
        // Nothing answers at 192.0.2.1 (an address for examples). The check must give up in time.
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'mysql://none:none@192.0.2.1:3306/none';
        $client = static::createClient();

        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(503);
        // The check's own time, from the answer. The test's total time also includes the
        // request logger, which in tests runs before the response returns.
        $checkMs = json_decode((string) $client->getResponse()->getContent(), true)['totalDurationMs'];
        self::assertLessThan(5000, $checkMs, sprintf('the database check took %.0f ms', $checkMs));
    }
}
