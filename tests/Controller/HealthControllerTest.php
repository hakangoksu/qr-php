<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    private ?string $originalDatabaseUrl = null;

    protected function setUp(): void
    {
        // Port 1 on localhost refuses connections immediately, so the database check
        // fails fast instead of waiting for a connect timeout.
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

        self::assertResponseStatusCodeSame(200);
        self::assertSame('Unhealthy', $client->getResponse()->getContent());
    }
}
