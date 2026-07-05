<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Contracts\Application\MiddlewareInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;

/**
 * Führt Aufgaben aus, NACHDEM die eigentliche Action beendet wurde (Terminate Phase).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class TerminateMailQueueMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MailServiceInterface $mailService,
        private ConfigInterface $config,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        // 1. Zuerst die Action (und eventuelle weitere Middlewares) ausführen
        $response = $next($request);

        // 2. Danach (Terminate) die Warteschlange abarbeiten
        try {
            // FIX: Prüft dynamisch, ob die Schnittstelle die Funktion anbietet, statt auf einen Klassentyp zu prüfen!
            if (\method_exists($this->mailService, 'processQueue')) {
                // Dynamisches Limit laden (Fallback auf 3, falls in der Config gelöscht)
                $limit = (int) $this->config->get('mail_queue_limit_web', 3);
                $this->mailService->processQueue($limit);
            }
        } catch (\Throwable) {
            // Still fail, Nutzer soll seinen Response bekommen
        }

        return $response;
    }
}
