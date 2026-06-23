<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\MailQueueRepositoryInterface;
use App\Core\Entity\MailJob;

/**
 * TODO
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class JsonMailQueueRepository implements MailQueueRepositoryInterface
{
    use JsonTransactionTrait;
    use SafeJsonWriterTrait;

    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    public function enqueue(MailJob $job): void
    {
        $path    = $this->config->getStoragePath($this->config->get('storage_config')['mail_queue']['file']);
        $queue   = \file_exists($path) ? JsonHelper::read($path) : [];
        $queue[] = ['id' => $job->id, 'recipient' => $job->recipient, 'subject' => $job->subject, 'template' => $job->template, 'data' => $job->data, 'attempts' => $job->attempts, 'created_at' => $job->createdAt->format('Y-m-d H:i:s')];
        $this->writeJsonSafely($path, $queue);
    }

    public function processBatch(int $limit, callable $processor): int
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['mail_queue']['file']);
        if (! \file_exists($path)) {
            return 0;
        }

        $sentCount = 0;
        $this->executeJsonTransaction($path, function (array &$queue) use ($limit, $processor, &$sentCount) {
            if (empty($queue)) {
                return false;
            }
            $actualLimit = \min($limit, \count($queue));
            for ($i = 0; $i < $actualLimit; ++$i) {
                $item = \array_shift($queue);

                try {
                    $processor($item['recipient'], $item['subject'], $item['template'], $item['data']);
                    ++$sentCount;
                } catch (\Throwable $t) {
                    $item['attempts'] = ($item['attempts'] ?? 0) + 1;
                    if ($item['attempts'] < 3) {
                        $queue[] = $item;
                    }
                }
            }

            return true;
        });

        return $sentCount;
    }

    public function import(array $data): void
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['mail_queue']['file']);
        $this->writeJsonSafely($path, \array_values($data));
    }
}
