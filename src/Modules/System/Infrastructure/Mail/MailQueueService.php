<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Mail;

use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\System\Domain\MailJob;
use App\Modules\System\Domain\MailQueueRepositoryInterface;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use Exception;

/**
 * Service für die asynchrone E-Mail-Verarbeitung über eine Warteschlange.
 */
final readonly class MailQueueService implements MailServiceInterface
{
    public function __construct(
        private MailQueueRepositoryInterface $repository,
        private MailServiceInterface $realMailService,
        private ClockInterface $clock,
    ) {
    }

    public function sendTemplate(string $recipient, string $subject, string $template, array $data, ?string $replyTo = null, int $priority = 50, array $attachments = []): bool
    {
        if ($attachments !== []) {
            $safeAtt = [];
            foreach ($attachments as $att) {
                $safeAtt[] = [
                    'name' => $att['name'],
                    'mime' => $att['mime'] ?? 'application/pdf',
                    'content_base64' => \base64_encode($att['content']),
                ];
            }
            $data['_attachments'] = $safeAtt;
        }

        $job = new MailJob(
            \uniqid('mq_'),
            $recipient,
            $replyTo,
            $subject,
            new TemplateKey($template),
            $data,
            0,
            $this->clock->now(),
            $priority,
        );
        $this->repository->enqueue($job);

        return true;
    }

    public function processQueue(int $limit = 5): int
    {
        return $this->repository->processBatch($limit, function (string $rec, string $sub, string $tpl, array $dat, ?string $replyTo): void {
            $attachments = [];
            if (!empty($dat['_attachments'])) {
                foreach ($dat['_attachments'] as $att) {
                    $attachments[] = [
                        'name' => $att['name'],
                        'mime' => $att['mime'],
                        'content' => \base64_decode($att['content_base64'], true),
                    ];
                }
                unset($dat['_attachments']);
            }

            $result = $this->realMailService->sendTemplate($rec, $sub, $tpl, $dat, $replyTo, 50, $attachments);

            if ($result !== true) {
                throw new Exception((string) $result);
            }
        });
    }
}
