<?php

declare(strict_types=1);

namespace App\Core\Entity;

use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;

final readonly class MailJob
{
    public function __construct(
        public string $id,
        public string $recipient,
        public ?string $replyTo,
        public string $subject,
        public TemplateKey $template,
        public array $data,
        public int $attempts,
        public DateTimeImmutable $createdAt,
        public int $priority = 50,
    ) {
    }
}
