<?php

namespace App\Api;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * An unknown URL under /api gets a JSON 404, not Symfony's HTML error page.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final class ApiNotFoundSubscriber
{
    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $exception = $event->getThrowable();

        // Only URLs that matched no route. API Platform handles its own errors.
        if (!$exception instanceof NotFoundHttpException
            || !str_starts_with($request->getPathInfo(), '/api/')
            || $request->attributes->has('_route')) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'type' => 'https://tools.ietf.org/html/rfc9110#section-15.5.5',
            'title' => 'Not Found',
            'status' => 404,
            'detail' => sprintf('There is no API endpoint at %s.', $request->getPathInfo()),
        ], 404, ['Content-Type' => 'application/problem+json']));
    }
}
