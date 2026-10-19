<?php

namespace App\Tests;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Testcontainers\Container\StartedGenericContainer;
use Testcontainers\Modules\MariaDBContainer;
use Testcontainers\Wait\WaitForExec;

/**
 * Base class for tests that need a database. Starts one temporary MariaDB container for
 * the whole test run and empties the table before every test. Needs Docker.
 */
abstract class DatabaseTestCase extends WebTestCase
{
    private static ?StartedGenericContainer $mariadb = null;
    private static ?string $databaseUrl = null;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $url = self::startDatabase();
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $url;

        $this->client = static::createClient();
        // Empty table for every test. TRUNCATE also starts the ids at 1 again.
        $this->connection()->executeStatement('TRUNCATE TABLE contacts');
    }

    protected function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    private static function startDatabase(): string
    {
        if (null !== self::$databaseUrl) {
            return self::$databaseUrl;
        }

        // In tests doctrine.yaml adds "_test" to the database name, so the URL says
        // "contactqr" and the database is "contactqr_test".
        self::$mariadb = (new MariaDBContainer('11.8'))
            ->withMariaDBDatabase('contactqr_test')
            ->withMariaDBUser('contactqr', 'contactqr')
            // The first start of MariaDB can take longer than the default 15 seconds.
            ->withWait(new WaitForExec(['mariadb-admin', 'ping', '-h', '127.0.0.1'], null, 60000))
            ->start();
        // Remove the container when PHP exits.
        register_shutdown_function(static fn () => self::$mariadb?->stop());

        self::$databaseUrl = sprintf(
            'mysql://contactqr:contactqr@%s:%d/contactqr?charset=utf8mb4',
            self::$mariadb->getHost(),
            self::$mariadb->getMappedPort(3306),
        );
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = self::$databaseUrl;

        // Run the migrations once, like production does.
        $kernel = static::bootKernel();
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $exitCode = $application->run(new ArrayInput(['command' => 'doctrine:migrations:migrate', '--no-interaction' => true]), $output);
        static::ensureKernelShutdown();
        if (0 !== $exitCode) {
            throw new \RuntimeException("Migrations failed:\n".$output->fetch());
        }

        return self::$databaseUrl;
    }
}
