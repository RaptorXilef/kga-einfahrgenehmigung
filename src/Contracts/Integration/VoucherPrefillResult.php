<?php

declare(strict_types=1);

namespace App\Contracts\Integration;

/**
 * Globales Integrations-DTO für die modulsichere Übergabe von Gutschein-Vorbefüllungen
 * an das Permit-Antragsformular (verhindert direkte Modul-Abhängigkeiten).
 */
final readonly class VoucherPrefillResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $code,
        public string $reason,
        public string $templateKey,
        public array $data,
    ) {
    }
}
