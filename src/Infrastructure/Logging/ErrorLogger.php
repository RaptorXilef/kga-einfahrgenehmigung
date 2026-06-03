<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Contracts\Config\ConfigInterface;

// TODO DOCBLOCK
final readonly class ErrorLogger
{
    public function __construct(private ConfigInterface $config)
    {
    }

    // TODO DOCBLOCK
    /**
     * Schreibt eine Throwable (Exception/Error) in die system_error.log
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

        @\file_put_contents($logFile, $message, \FILE_APPEND);
    }
}
