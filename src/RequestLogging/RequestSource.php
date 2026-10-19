<?php

namespace App\RequestLogging;

final class RequestSource
{
    public const UI = 'UI';
    public const API = 'API';

    /**
     * The spec asks for "UI or API". The REST API, its docs and the health check are API.
     * Everything else is UI.
     */
    public static function classify(string $path): string
    {
        foreach (['/api', '/health'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return self::API;
            }
        }

        return self::UI;
    }
}
