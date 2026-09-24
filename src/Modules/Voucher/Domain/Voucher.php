<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * Die Aggregatwurzel (Aggregate Root) für einen Gutschein.
 * 100% autark, hält sich immer in einem validen Zustand.
 */
final class Voucher
{
    public function __construct(
        public readonly string $code,
        public readonly string $templateKey,
        public readonly string $reason,
        public readonly string $type, // 'fixed', 'percent', 'free'
        public readonly float $value,
        public readonly bool $isMultiUse,
        public readonly int $maxUses,
        private int $currentUses,
        public readonly ?DateTimeImmutable $expiresAt,
        private string $status, // 'aktiv', 'deaktiviert'
        public readonly array $prefillData,
        public readonly string $createdBy,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Named Constructor für sauberes Erzeugen neuer Gutscheine.
     * VSA FIX: $createdAt wird jetzt vom Aufrufer injiziert (Domain pureness)
     */
    public static function create(
        string $code,
        string $templateKey,
        string $reason,
        string $type,
        float $value,
        bool $isMultiUse,
        int $maxUses,
        ?DateTimeImmutable $expiresAt,
        array $prefillData,
        string $createdBy,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $code,
            $templateKey,
            $reason,
            $type,
            $value,
            $isMultiUse,
            $maxUses,
            0, // Startet mit 0 Nutzungen
            $expiresAt,
            'aktiv',
            $prefillData,
            $createdBy,
            $createdAt,
        );
    }

    public function getCurrentUses(): int
    {
        return $this->currentUses;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'aktiv';
    }

    public function isDeactivated(): bool
    {
        return $this->status === 'deaktiviert';
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        if (!$this->expiresAt instanceof DateTimeImmutable) {
            return false;
        }

        return $this->expiresAt < $now;
    }

    /**
     * Erhöht den Nutzungszähler. Schlägt fehl, wenn Limit erreicht.
     */
    public function recordUsage(): void
    {
        if (!$this->isMultiUse && $this->currentUses >= 1) {
            throw new DomainException('Dieser Einmal-Gutschein wurde bereits verwendet.');
        }

        if ($this->isMultiUse && $this->currentUses >= $this->maxUses) {
            throw new DomainException('Das maximale Nutzungslimit für diesen Gutschein ist erreicht.');
        }

        ++$this->currentUses;
    }

    public function deactivate(): void
    {
        $this->status = 'deaktiviert';
    }

    public function activate(): void
    {
        $this->status = 'aktiv';
    }
}
