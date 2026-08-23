<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\CronStateRepositoryInterface;

final readonly class FileCronStateRepository implements CronStateRepositoryInterface
{
    public function __construct(private ConfigInterface $config)
    {
    }

    public function getLastRunTime(): int
    {
        $rootPath = \rtrim((string) $this->config->get('root_path', ''), '/\\');
        $path = $rootPath . '/logs/last_cron_run.txt';

        return \file_exists($path) ? (int) \file_get_contents($path) : 0;
    }

    public function setLastRunTime(int $timestamp): void
    {
        $rootPath = \rtrim((string) $this->config->get('root_path', ''), '/\\');
        $path = $rootPath . '/logs/last_cron_run.txt';
        $dir = \dirname($path);

        // Sicherstellen, dass das logs/ Verzeichnis existiert, da file_put_contents
        // sonst fehlschlägt und der Cron bei JEDEM Seitenaufruf in eine Dauerschleife läuft.
        if (!\is_dir($dir)) {
            @\mkdir($dir, 0o755, true);
        }

        \file_put_contents($path, (string) $timestamp, \LOCK_EX);
    }
}
