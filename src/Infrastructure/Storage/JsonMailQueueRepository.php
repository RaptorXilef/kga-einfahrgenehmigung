<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\MailQueueRepositoryInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Core\Entity\MailJob;
use Throwable;

final readonly class JsonMailQueueRepository implements MailQueueRepositoryInterface
{
    use JsonTransactionTrait;
    use SafeJsonWriterTrait;

    public function __construct(
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function enqueue(MailJob $job): void
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['mail_queue']['file']);
        $queue = \file_exists($path) ? $this->jsonHelper->read($path) : [];

        $queue[] = [
            'id' => $job->id,
            'recipient' => $job->recipient,
            'subject' => $job->subject,
            'template' => $job->template->value, // Extrahieren!
            'data' => $job->data,
            'attempts' => $job->attempts,
            'created_at' => $job->createdAt->format('Y-m-d H:i:s'),
        ];

        $this->writeJsonSafely($path, $queue);
    }

    public function processBatch(int $limit, callable $processor, array $allowedTemplates = []): int
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['mail_queue']['file']);

        if (!\file_exists($path)) {
            return 0;
        }

        $sentCount = 0;

        $this->executeJsonTransaction($path, function (array &$queue) use ($limit, $processor, &$sentCount): bool {
            if ($queue === []) {
                return false;
            }

            // #Email #Priorität #Query #Warteschlange
            // PRIORISIERUNG: 0 = Höchste, 9 = Niedrigste
            \usort($queue, function (array $a, array $b): int {
                $getPrio = fn ($template): int => match ($template) {
                    'magic_link', 'verify_email' => 0,
                    'permit_a4_document' => 1,
                    'payment_request' => 2,
                    'permit_cancelled' => 3,
                    'board_notification' => 5,
                    'payment_reminder' => 9,
                    default => 7,
                };

                $aPrio = $getPrio($a['template'] ?? '');
                $bPrio = $getPrio($b['template'] ?? '');

                if ($aPrio !== $bPrio) {
                    return $aPrio <=> $bPrio;
                }

                return ($a['created_at'] ?? 0) <=> ($b['created_at'] ?? 0);
            });

            $actualLimit = \min($limit, \count($queue));

            for ($i = 0; $i < $actualLimit; ++$i) {
                $item = \array_shift($queue);

                try {
                    $processor($item['recipient'], $item['subject'], $item['template'], $item['data']);
                    ++$sentCount;
                } catch (Throwable $t) {
                    $rootPath = \rtrim((string) $this->config->get('root_path', ''), '/\\');
                    $logPath = $rootPath . '/logs/mail_queue_errors.log';
                    $logMsg = '[' . \date('d-M-Y H:i:s e') . "] MailQueue Error [ID {$item['id']}]: " . $t->getMessage() . "\n";
                    @\file_put_contents($logPath, $logMsg, \FILE_APPEND | \LOCK_EX);

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

    public function findAllQueue(): array
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['mail_queue']['file']);
        if (!\file_exists($path)) {
            return [];
        }

        $queue = $this->jsonHelper->read($path);
        \usort($queue, fn (array $a, array $b): int => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0));

        return $queue;
    }

    public function findById(string $id): ?array
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['mail_queue']['file']);
        if (!\file_exists($path)) {
            return null;
        }

        $queue = $this->jsonHelper->read($path);
        foreach ($queue as $item) {
            if (($item['id'] ?? '') === $id) {
                return $item;
            }
        }

        return null;
    }

    public function delete(string $id): void
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['mail_queue']['file']);
        $this->executeJsonTransaction($path, function (array &$queue) use ($id): bool {
            foreach ($queue as $index => $item) {
                if (($item['id'] ?? '') === $id) {
                    unset($queue[$index]);
                    $queue = \array_values($queue);

                    return true;
                }
            }

            return false;
        });
    }
}
