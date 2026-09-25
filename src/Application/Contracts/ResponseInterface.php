<?php

declare(strict_types=1);

namespace App\Application\Contracts;

/**
 * Basis-Vertrag für alle HTTP-Antwortobjekte (HTML, JSON, Redirect, File-Download, Binary).
 */
interface ResponseInterface
{
    /**
     * Sendet die HTTP-Header sowie den Payload an den Client und beendet den Request-Lebenszyklus.
     */
    public function send(): void;
}
