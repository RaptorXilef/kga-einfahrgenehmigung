<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Contracts\Config\ConfigInterface;

/**
 * Logger-Infrastruktur für Systemfehler.
 *
 * Schreibt geworfene Exceptions und fatale Fehler revisionssicher in eine lokale Datei.
 *
 * Path: src/Infrastructure/Logging/ErrorLogger.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ErrorLogger
{
    public function __construct(private ConfigInterface $config)
    {
    }

    /**
     * Schreibt ein Throwable (Exception/Error) formatiert in die system_error.log.
     * Erstellt den Ordner, falls er nicht existiert.
     *
     * @param \Throwable $throwable Der aufgetretene Fehler samt Stacktrace.
     */
    public function logThrowable(\Throwable $throwable): void
    {
        $logDir = \rtrim((string) $this->config->get('root_path'), '/\\') . '/storage/logs';

        if (! \is_dir($logDir)) {
            @\mkdir($logDir, 0o755, true);
        }

        $logFile = $logDir . '/system_error.log';

        $timestamp = \date('Y-m-d H:i:s');
        $message   = \sprintf(
            "[%s] [%s] %s in %s:%d\nStack Trace:\n%s\n%s\n",
            $timestamp,
            \get_class($throwable),
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine(),
            $throwable->getTraceAsString(),
            \str_repeat('=', 80),
        );

        @\file_put_contents(
            $logFile,
            $message,
            \FILE_APPEND | \LOCK_EX,
        );
    }
}
