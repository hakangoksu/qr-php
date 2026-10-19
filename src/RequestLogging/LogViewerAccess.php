<?php

namespace App\RequestLogging;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The request log shows visitors' IP addresses, so it needs a password (HTTP Basic,
 * LOG_VIEWER_PASSWORD). No password set: the log stays closed.
 *
 * Runs right after the router (priority 31) and checks the matched route, not the path.
 * So /%6cogs, which the router reads as /logs, is checked too.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 31)]
final class LogViewerAccess
{
    public const USER_NAME = 'reviewer';

    /** The log page (LogController) and the log API (RequestLogListResource). */
    public const LOG_ROUTES = ['logs', '_api_/v1/request-logs_get'];

    public function __construct(
        #[Autowire('%env(LOG_VIEWER_PASSWORD)%')]
        private readonly string $password,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !\in_array($event->getRequest()->attributes->get('_route'), self::LOG_ROUTES, true)) {
            return;
        }

        if ('' === $this->password) {
            $event->setResponse(new Response('The request log is disabled: no LOG_VIEWER_PASSWORD is configured.', Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/plain']));

            return;
        }

        $request = $event->getRequest();
        // PHP splits "Authorization: Basic ..." into PHP_AUTH_USER / PHP_AUTH_PW.
        $user = $request->getUser();
        $given = $request->getPassword();

        // hash_equals takes the same time for every wrong password, so timing tells nothing.
        if (self::USER_NAME !== $user || null === $given || !hash_equals(hash('sha256', $this->password), hash('sha256', $given))) {
            $event->setResponse(new Response('', Response::HTTP_UNAUTHORIZED, [
                // Makes the browser show its login dialog.
                'WWW-Authenticate' => 'Basic realm="Request log", charset="UTF-8"',
            ]));
        }
    }
}
