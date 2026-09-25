<?php

declare(strict_types=1);

namespace App\Contracts\System;

/**
 * Port für das serverseitige Senden von Webanalyse-Events (z.B. Google Analytics 4 Measurement Protocol).
 * Entkoppelt die HTTP-Middlewares von nativen cURL-Netzwerkaufrufen.
 */
interface AnalyticsTrackerInterface
{
    public function trackPageView(string $clientId, int $sessionId, string $pageLocation, string $pageTitle): void;
}
