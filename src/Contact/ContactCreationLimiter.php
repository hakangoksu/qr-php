<?php

namespace App\Contact;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * A limit per IP on new contacts, for the form and the API. Without it one client could
 * fill the database. Reading is not limited.
 * Checks the matched route, not the path, so /api/v1/%63ontacts counts too.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 31)]
final class ContactCreationLimiter
{
    public function __construct(
        // framework.rate_limiter.contact_creation in config/packages/rate_limiter.yaml
        #[Autowire(service: 'limiter.contact_creation')]
        private readonly RateLimiterFactory $limiter,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod('POST')
            || !\in_array($request->attributes->get('_route'), ['contact_new', '_api_/v1/contacts_post'], true)) {
            return;
        }

        // The client's address (no proxy is trusted).
        $limit = $this->limiter->create($request->getClientIp() ?? 'unknown')->consume();
        if ($limit->isAccepted()) {
            return;
        }

        $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
        $event->setResponse(new JsonResponse([
            'type' => 'https://tools.ietf.org/html/rfc6585#section-4',
            'title' => 'Too many requests',
            'status' => 429,
            'detail' => sprintf('At most %d new contacts per minute. Try again in a minute.', $limit->getLimit()),
        ], Response::HTTP_TOO_MANY_REQUESTS, [
            'Content-Type' => 'application/problem+json',
            'Retry-After' => (string) $retryAfter,
        ]));
    }
}
