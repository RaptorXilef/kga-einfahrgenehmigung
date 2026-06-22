<?php

declare(strict_types=1);

namespace App\Core\Entity;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class Voucher
{
    public function __construct(
        public string $code,
        public string $reason,
        public string $templateKey,
        public string $type,
        public float $value,
        public bool $multiUse,
        public int $maxUses,
        public int $usesCount,
        public ?\DateTimeImmutable $expiresAt,
        public string $dateMode,
        public string $createdBy,
        public \DateTimeImmutable $createdAt,
        public string $status,
        public array $data,
    ) {
    }

    public function isDeactivated(): bool
    {
        return $this->status === 'deaktiviert';
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < $now;
    }

    public function isDepleted(): bool
    {
        return $this->multiUse && $this->maxUses > 0 && $this->usesCount >= $this->maxUses;
    }

    public function isValid(\DateTimeImmutable $now): bool
    {
        if ($this->isDeactivated()) {
            return false;
        }
        if ($this->isExpired($now)) {
            return false;
        }
        if ($this->isDepleted()) {
            return false;
        }

        return true;
    }

    public function redeem(): self
    {
        return new self(
            $this->code,
            $this->reason,
            $this->templateKey,
            $this->type,
            $this->value,
            $this->multiUse,
            $this->maxUses,
            $this->usesCount + 1,
            $this->expiresAt,
            $this->dateMode,
            $this->createdBy,
            $this->createdAt,
            $this->status,
            $this->data,
        );
    }

    public function withStatus(string $newStatus): self
    {
        return new self(
            $this->code,
            $this->reason,
            $this->templateKey,
            $this->type,
            $this->value,
            $this->multiUse,
            $this->maxUses,
            $this->usesCount,
            $this->expiresAt,
            $this->dateMode,
            $this->createdBy,
            $this->createdAt,
            $newStatus,
            $this->data,
        );
    }
}
