<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\LockManagerInterface;

final readonly class FileLockManager implements LockManagerInterface
{
    public function __construct(private ConfigInterface $config)
    {
    }

    public function executeWithLock(string $lockName, callable $operation): mixed
    {
        $rootPath = \rtrim((string) $this->config->get('root_path', ''), '/\\');
        $lockFile = $rootPath . "/logs/{$lockName}.lock";

        $fp = @\fopen($lockFile, 'c');
        if ($fp) {
            \flock($fp, \LOCK_EX);
        }

        try {
            return $operation();
        } finally {
            if ($fp) {
                \flock($fp, \LOCK_UN);
                \fclose($fp);
            }
        }
    }
}
