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
     * Erstellt einen Gutschein mit optionalen Vorbefüllungs-Daten.
     * Prüft auf Einmaligkeit gegen aktive und archivierte Codes.
     *
     * @param array<string, mixed> $prefillData Felder wie 'name', 'parzelle', 'kennzeichen'
     */
    public function createVoucher(
        string $reason,
        string $createdBy,
        string $templateKey,
        array $prefillData = [],
    ): string {
        $activeVouchers = $this->loadVouchers();
        $archivedItems  = $this->loadArchive();

        // Wir sammeln alle bereits vergebenen Codes in einer Liste für den Abgleich
        $alreadyUsedCodes = \array_keys($activeVouchers);
        foreach ($archivedItems as $archivedEntry) {
            $alreadyUsedCodes[] = $archivedEntry['code'];
        }

        // Schleife: Generiere so lange neu, bis der Code wirklich einmalig ist
        do {
            $newGeneratedCode = 'GUT-' . \strtoupper(\bin2hex(\random_bytes(4)));
        } while (\in_array($newGeneratedCode, $alreadyUsedCodes, true));

        $activeVouchers[$newGeneratedCode] = [
            'code'         => $newGeneratedCode,
            'reason'       => $reason,
            'template_key' => $templateKey,
            'data'         => $prefillData,
            'created_by'   => $createdBy,
            'created_at'   => \date('Y-m-d H:i:s'),
        ];

        $this->saveVouchers($activeVouchers);

        return $newGeneratedCode;
    }

    /**
     * Löst einen Gutschein ein und archiviert ihn v0.16.0
     *
     * @param array<string, mixed> $userData Daten des Pächters (Name, Email, Parzelle)
     */
    public function useVoucher(string $code, array $userData = []): ?array
    {
        $vouchers = $this->loadVouchers();
        if (! isset($vouchers[$code])) {
            return null;
        }

        $voucher = $vouchers[$code];

        // Archiv-Eintrag erstellen
        $archivePath = $this->config->get('root_path') . '/storage/vouchers_archive.json';
        $archive     = \file_exists($archivePath) ? \json_decode((string) \file_get_contents($archivePath), true) : [];

        $archive[] = [
            'code'        => $code,
            'reason'      => $voucher['reason'],
            'template'    => $voucher['template_key'],
            'redeemed_at' => \date('Y-m-d H:i:s'),
            'user_name'   => $userData['name'] ?? 'Unbekannt',
            'user_plot'   => $userData['parzelle'] ?? '?',
            'user_email'  => $userData['email'] ?? '?',
        ];

        \file_put_contents($archivePath, \json_encode($archive, \JSON_PRETTY_PRINT));

        // Aus aktiven Gutscheinen löschen
        unset($vouchers[$code]);
        $this->saveVouchers($vouchers);

        return $voucher;
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

    /**
     * Deaktiviert einen Gutschein (setzt ihn auf 'deaktiviert' statt zu löschen)
     */
    public function deactivateVoucher(string $code, string $reason): bool
    {
        $vouchers = $this->loadVouchers();
        if (! isset($vouchers[$code])) {
            return false;
        }

        $vouchers[$code]['status'] = 'deaktiviert';
        $vouchers[$code]['note']   = $reason;

        $this->saveVouchers($vouchers);

        return true;
    }

    /**
     * Lädt das Archiv der eingelösten Gutscheine v0.17.0
     */
    public function loadArchive(): array
    {
        $path = $this->config->get('root_path') . '/storage/vouchers_archive.json';
        if (! \file_exists($path)) {
            return [];
        }

        return \json_decode((string) \file_get_contents($path), true) ?? [];
    }

    /**
     * Ändert den Status eines Gutscheins (z.B. aktiv/deaktiviert)
     */
    public function toggleStatus(string $code, string $status): bool
    {
        $vouchers = $this->loadVouchers();
        if (! isset($vouchers[$code])) {
            return false;
        }

        $vouchers[$code]['status'] = $status;
        $this->saveVouchers($vouchers);

        return true;
    }
}
