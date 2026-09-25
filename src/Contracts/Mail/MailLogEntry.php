<?php

declare(strict_types=1);

namespace App\Contracts\Mail;

use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;

/**
 * Unveränderliches Datenobjekt für einen E-Mail-Versandprotokoll-Eintrag.
 * Lebt im Contracts-Layer, damit MailLogInterface modulsicher ohne Rückwärts-Abhängigkeit auf System bleibt.
 */
final readonly class MailLogEntry
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $id,
        public DateTimeImmutable $timestamp,
        public string $recipient,
        public ?string $replyTo,
        public string $subject,
        public TemplateKey $template,
        public string $status,
        public array $data,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->status === 'Erfolg';
    }
}
