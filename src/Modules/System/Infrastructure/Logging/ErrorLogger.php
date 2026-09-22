<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Logging;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\ErrorLoggerInterface;
use App\SharedKernel\Infrastructure\Storage\SafeJsonWriterTrait;
use RuntimeException;
use Throwable;

final readonly class ErrorLogger implements ErrorLoggerInterface
{
    use SafeJsonWriterTrait;

    public function __construct(private ConfigInterface $config)
    {
    }

    public function logThrowable(Throwable $throwable): void
    {
        $rootPath = \rtrim((string) $this->config->get('root_path', ''), '/\\');
        $logDir = $rootPath . '/logs';

        if (!\is_dir($logDir)) {
            @\mkdir($logDir, 0o755, true);
        }

        $logFile = $logDir . '/system_error.log';
        $timestamp = APP_REQUEST_TIME_STR;

        $message = \sprintf(
            "[%s] [%s] %s in %s:%d\nStack Trace:\n%s\n%s\n",
            $timestamp,
            $throwable::class,
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine(),
            $throwable->getTraceAsString(),
            \str_repeat('=', 80),
        );

        $result = @\file_put_contents(
            $logFile,
            $message,
            \FILE_APPEND | \LOCK_EX,
        );

        if ($result === false) {
            throw new RuntimeException('Kritischer Schreibfehler: system_error.log voll.');
        }
    }
}
