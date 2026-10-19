<?php

namespace App\Controller;

use App\RequestLogging\RequestLogQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The request log page: filters and a table. Same search as the log API.
 */
final class LogController extends AbstractController
{
    #[Route('/logs', name: 'logs', methods: ['GET'])]
    public function __invoke(Request $request, RequestLogQuery $logs): Response
    {
        $q = $request->query;

        // The page ignores bad filter values. The form itself only sends valid ones.
        $filters = [
            'source' => \in_array($q->get('source'), ['UI', 'API'], true) ? $q->get('source') : null,
            'status' => preg_match('/^[1-5]\d\d$/', (string) $q->get('status')) ? (int) $q->get('status') : null,
            'method' => self::text($q->get('method')),
            'path' => self::text($q->get('path')),
            'from' => self::dateTime($q->get('from')),
            'to' => self::dateTime($q->get('to')),
        ];
        $page = preg_match('/^[1-9]\d*$/', (string) $q->get('page')) ? (int) $q->get('page') : 1;

        return $this->render('logs/index.html.twig', [
            'result' => $logs->search($filters, $page),
            'filters' => $filters,
        ]);
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    private static function dateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            return null;
        }

        // The date input sends "2026-10-10T06:00", read as UTC.
        return \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, new \DateTimeZone('UTC')) ?: null;
    }
}
