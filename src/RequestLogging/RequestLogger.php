<?php

namespace App\RequestLogging;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Writes one row for every request, page or API.
 * kernel.terminate runs after the response is sent, so the user does not wait for the
 * INSERT. It also runs for errors, with the final status code.
 */
#[AsEventListener(event: KernelEvents::TERMINATE)]
final class RequestLogger
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly RequestLogRetention $retention,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        // REQUEST_TIME_FLOAT: when PHP got the request. The time includes starting Symfony.
        $started = (float) $request->server->get('REQUEST_TIME_FLOAT', microtime(true));
        $durationMs = (microtime(true) - $started) * 1000;

        $path = $request->getPathInfo();   // without the query string: it can carry personal data
        $userAgent = $request->headers->get('User-Agent');

        try {
            $this->connection->insert('request_logs', [
                'timestamp' => \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $started))->format('Y-m-d H:i:s.u'),
                // The client's address. X-Forwarded-For only from a trusted proxy (there is none).
                'ip_address' => $request->getClientIp(),
                'method' => $request->getMethod(),
                'path' => mb_substr($path, 0, 2048),
                'status_code' => $response->getStatusCode(),
                'user_agent' => null === $userAgent || '' === $userAgent ? null : mb_substr($userAgent, 0, 512),
                'source' => RequestSource::classify($path),
                'duration_ms' => round($durationMs, 3),
            ]);
            // Now and then delete old rows (RequestLogRetention).
            $this->retention->maybePrune();
        } catch (\Throwable $exception) {
            // If the log write fails, only note it in the app log. The response is already sent.
            $this->logger->error('Could not store the request log for {method} {path}', [
                'method' => $request->getMethod(),
                'path' => $path,
                'exception' => $exception,
            ]);
        }
    }
}
