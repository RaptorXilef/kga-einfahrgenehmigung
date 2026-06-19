<?php

declare(strict_types=1);

namespace App\Application\Response;

/**
 * Hilfsklasse für einheitliche JSON-HTTP-Antworten.
 * Kapselt die JSON-Codierung, HTTP-Statuscodes und CSRF-Sicherheitsprüfungen
 * für API- und AJAX-Endpunkte ab.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class JsonResponse
{
    /**
     * Sendet eine generische JSON-Antwort und beendet den Request.
     */
    public static function send(array $data, int $statusCode = 200): void
    {
        \http_response_code($statusCode);
        \header('Content-Type: application/json; charset=utf-8');
        echo \json_encode(
            $data,
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT,
        );
        exit;
    }

    /**
     * Sendet eine Erfolgsantwort (200 OK) und mischt das 'success' => true Flag bei.
     */
    public static function success(array $data = []): void
    {
        self::send(\array_merge(['success' => true], $data), 200);
    }

    /**
     * Sendet eine Fehlerantwort (Standard: 400 Bad Request).
     */
    public static function error(string $message, int $statusCode = 400): void
    {
        self::send(['success' => false, 'error' => $message], $statusCode);
    }

    /**
     * Standardisierter 401 Unauthorized Fehler.
     */
    public static function unauthorized(string $message = 'Unauthorized: Invalid Security Token'): void
    {
        self::error($message, 401);
    }

    /**
     * Validiert das CSRF-Token direkt. Bricht mit 401 ab, wenn ungültig.
     */
    public static function enforceCsrfProtection(): void
    {
        $providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken  = $_SESSION['csrf_token'] ?? '';

        if ($sessionToken === '' || ! \hash_equals($sessionToken, $providedToken)) {
            self::unauthorized();
        }
    }
}
