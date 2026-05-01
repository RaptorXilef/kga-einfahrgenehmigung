<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * @file src/Core/Service/VoucherService.php
 */

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

final readonly class VoucherService
{
    private string $storagePath;

    public function __construct(private ConfigInterface $config)
    {
        $this->storagePath = $this->config->get('root_path') . '/storage/vouchers.json';
    }

    /**
     * Erstellt einen neuen Einmal-Gutschein.
     */
    public function createVoucher(string $reason, string $createdBy): string
    {
        $code     = 'GUT-' . \strtoupper(\bin2hex(\random_bytes(4)));
        $vouchers = $this->loadVouchers();

        $vouchers[$code] = [
            'code'       => $code,
            'reason'     => $reason,
            'created_by' => $createdBy,
            'created_at' => \date('Y-m-d H:i:s'),
            'used'       => false,
        ];

        $this->saveVouchers($vouchers);

        return $code;
    }

    /**
     * Prüft einen Code und entwertet ihn, wenn er gültig ist.
     *
     * @return array<string, mixed>|null
     */
    public function useVoucher(string $code): ?array
    {
        $vouchers = $this->loadVouchers();
        if (! isset($vouchers[$code]) || $vouchers[$code]['used'] === true) {
            return null;
        }

        $vouchers[$code]['used']    = true;
        $vouchers[$code]['used_at'] = \date('Y-m-d H:i:s');
        $this->saveVouchers($vouchers);

        return $vouchers[$code];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function loadVouchers(): array
    {
        if (! \file_exists($this->storagePath)) {
            return [];
        }

        return \json_decode((string) \file_get_contents($this->storagePath), true) ?? [];
    }

    /**
     * @param array<string, array<string, mixed>> $vouchers
     */
    private function saveVouchers(array $vouchers): void
    {
        \file_put_contents($this->storagePath, \json_encode($vouchers, \JSON_PRETTY_PRINT));
    }
}
