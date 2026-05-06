<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Path: src/Core/Service/VoucherService.php
 */

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

final readonly class VoucherService
{
    private string $storagePath;

    public function __construct(
        private ConfigInterface $config,
        private ?\PDO $pdo, // Nullable
    ) {
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
        string $type = 'free', // NEU: free, fixed, percent
        float $value = 0.0,    // NEU: Betrag oder Prozent
        bool $multiUse = false, // NEU
        ?int $maxUses = 1,      // NEU
        ?string $customCode = null, // NEU: Optionaler individueller Code
        ?string $expiresAt = null, // NEU
        string $dateMode = 'fixed',  // NEU: 'fixed' oder 'flexible'
    ): string {
        $activeVouchers = $this->loadVouchers();
        $archivedItems  = $this->loadArchive(); // Hier wird die Datei der benutzten Codes geladen!

        // Wir sammeln alle bereits vergebenen Codes in einer Liste für den Abgleich
        $alreadyUsedCodes = \array_keys($activeVouchers);
        foreach ($archivedItems as $archivedEntry) {
            $alreadyUsedCodes[] = $archivedEntry['code']; // Füge benutzte Codes zur Sperrliste hinzu
        }

        // Logik für Code-Findung
        if ($customCode !== null && \trim($customCode) !== '') {
            $newGeneratedCode = \strtoupper(\trim($customCode));
            if (\in_array($newGeneratedCode, $alreadyUsedCodes, true)) {
                throw new \RuntimeException("Der Code '{$newGeneratedCode}' wurde bereits verwendet oder existiert schon.");
            }
        } else {
            // Schleife: Generiere so lange neu, bis der Code wirklich einmalig ist
            do {
                $newGeneratedCode = 'GUT-' . \strtoupper(\bin2hex(\random_bytes(4)));
            } while (\in_array($newGeneratedCode, $alreadyUsedCodes, true)); // Prüfe gegen die Sperrliste
        }

        $activeVouchers[$newGeneratedCode] = [
            'code'         => $newGeneratedCode,
            'reason'       => $reason,
            'template_key' => $templateKey,
            'type'         => $type,
            'value'        => $value,
            'multi_use'    => $multiUse,
            'max_uses'     => $maxUses,
            'uses_count'   => 0,
            'expires_at'   => $expiresAt, // NEU
            'date_mode'    => $dateMode,  // NEU
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

        $voucher = &$vouchers[$code];
        ++$voucher['uses_count'];

        // --- ARCHIV-LOGIK VIA CONFIG ---
        $arcCfg = $this->config->get('storage_config')['vouchers_archive'];

        $archiveEntry = [
            'code'        => $code,
            'reason'      => $voucher['reason'],
            'template'    => $voucher['template_key'],
            'redeemed_at' => \date('Y-m-d H:i:s'),
            'user_name'   => $userData['name'] ?? 'Unbekannt',
            'user_plot'   => $userData['parzelle'] ?? '?',
            'user_email'  => $userData['email'] ?? '?',
        ];

        if ($arcCfg['type'] === 'mysql' && $this->pdo) {
            // Direkt in die Datenbank-Tabelle schreiben
            $sql = "INSERT INTO {$arcCfg['table']} (code, redeemed_at, user_name, user_plot)
                    VALUES (:code, :redeemed_at, :user_name, :user_plot)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'code'        => $archiveEntry['code'],
                'redeemed_at' => $archiveEntry['redeemed_at'],
                'user_name'   => $archiveEntry['user_name'],
                'user_plot'   => $archiveEntry['user_plot'],
            ]);
        } else {
            // Klassisch JSON
            $archivePath = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $arcCfg['file'];
            $archive     = \file_exists($archivePath) ? \json_decode((string) \file_get_contents($archivePath), true) : [];
            $archive[]   = $archiveEntry;
            \file_put_contents($archivePath, \json_encode($archive, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
        }
        // --- ENDE ARCHIV-LOGIK ---

        // Lösch-Logik für den aktiven Gutschein
        $shouldDelete = ! ($voucher['multi_use'] ?? false);
        if (($voucher['multi_use'] ?? false) && (int) $voucher['max_uses'] > 0 && $voucher['uses_count'] >= $voucher['max_uses']) {
            $shouldDelete = true;
        }

        if ($shouldDelete) {
            // Aus aktiven Gutscheinen löschen
            unset($vouchers[$code]);
        }

        $this->saveVouchers($vouchers);

        return $voucher;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function loadVouchers(): array
    {
        $cfg = $this->config->get('storage_config')['vouchers'];

        if ($cfg['type'] === 'mysql') {
            if (! $this->pdo) {
                throw new \RuntimeException('Datenbank offline.');
            }
            $stmt     = $this->pdo->query("SELECT * FROM {$cfg['table']}");
            $rows     = $stmt->fetchAll();
            $vouchers = [];
            foreach ($rows as $r) {
                // MySQL TEXT Spalte wieder in Array wandeln
                $r['data']            = \json_decode((string) ($r['data'] ?? '{}'), true);
                $vouchers[$r['code']] = $r;
            }

            return $vouchers;
        }

        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];

        return \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
    }

    /**
     * @param array<string, array<string, mixed>> $vouchers
     */
    public function saveVouchers(array $vouchers): void
    {
        $cfg = $this->config->get('storage_config')['vouchers'];

        if ($cfg['type'] === 'mysql') {
            // Wir löschen die Tabelle und füllen sie neu (Einfachste Sync-Logik für REPLACE)
            $this->pdo->exec("DELETE FROM {$cfg['table']}");
            $sql = "INSERT INTO {$cfg['table']} (code, reason, template_key, type, value, multi_use, max_uses, uses_count, expires_at, date_mode, created_by, created_at, data)
                    VALUES (:code, :reason, :template_key, :type, :value, :multi_use, :max_uses, :uses_count, :expires_at, :date_mode, :created_by, :created_at, :data)";
            $stmt = $this->pdo->prepare($sql);

            foreach ($vouchers as $v) {
                $v['data']      = \json_encode($v['data'] ?? []); // Array für DB serialisieren
                $v['multi_use'] = (int) ($v['multi_use'] ?? 0);
                $stmt->execute($v);
            }

            return;
        }

        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
        \file_put_contents($path, \json_encode($vouchers, \JSON_PRETTY_PRINT));
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
        $cfg = $this->config->get('storage_config')['vouchers_archive'];

        if ($cfg['type'] === 'mysql') {
            return $this->pdo->query("SELECT * FROM {$cfg['table']} ORDER BY redeemed_at DESC")->fetchAll();
        }

        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];

        return \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
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
