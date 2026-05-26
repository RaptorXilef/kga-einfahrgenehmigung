<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

/**
 * Service für das Erstellen, Verwalten und Einlösen von Aktions- und Freigutscheinen.
 *
 * Erzeugt fälschungssichere Gutscheincodes, unterstützt Mehrfachnutzung ('multi_use'),
 * Verfallsdaten, Vorbefüllungs-Schablonen für Anträge und protokolliert Einlösungen revisionssicher im Archiv.
 * Kontext: Marketing- und Administrations-Subkomponente für das Gutscheinwesen.
 *
 * Path: src/Core/Service/VoucherService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class VoucherService
{
    public function __construct(
        private ConfigInterface $config,
        private ?\PDO $pdo, // Nullable
    ) {
    }

    /**
     * Generiert einen neuen Gutschein im System mit individuellen Restriktionen.
     * Überprüft Wunsch-Codes auf Einzigartigkeit gegen Live- und Archivbestände oder generiert ein 'GUT-'-Krypto-Token.
     *
     * @param string               $reason       Der Ausstellungsgrund (z.B. "Vorstandsentlastung").
     * @param string               $createdBy    Die ID oder der Name des ausstellenden Administrators.
     * @param string               $template_key Das verknüpfte Tarif-Template (z.B. 'std_7').
     * @param array<string, mixed> $prefillData  Optionale Stammdaten zur Zwangs-Vorbefüllung des Formulars.
     * @param string               $type         Der Rabatt-Typ ('free', 'fixed', 'percent').
     * @param float                $value        Der numerische Rabattwert (Betrag oder Prozentsatz).
     * @param bool                 $multiUse     True, wenn der Gutschein von mehreren Personen genutzt werden darf.
     * @param int|null             $maxUses      Maximale Einlösungsanzahl bei Multi-Use.
     * @param string|null          $customCode   Optionaler Wunsch-Code (z.B. "SOMMER2026").
     * @param string|null          $expiresAt    Optionales Ablaufdatum (Y-m-d).
     * @param string               $dateMode     Gültigkeitsmodus für Termine ('fixed' oder flexibel).
     *
     * @return string Der finale, registrierte Gutscheincode im System.
     */
    public function createVoucher(
        string $reason,
        string $createdBy,
        string $template_key,
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
                throw new \RuntimeException(
                    "Der Code '{$newGeneratedCode}' wurde bereits verwendet oder existiert schon.",
                );
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
            'template_key' => $template_key,
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
     * Löst einen Gutscheincode ein, inkrementiert Nutzungszähler und schreibt ein Revisionsprotokoll ins Archiv.
     * Löscht Single-Use-Gutscheine oder erschöpfte Multi-Use-Gutscheine direkt aus dem Live-Bestand.
     *
     * @param string               $code     Der einzulösende Gutscheincode.
     * @param array<string, mixed> $userData Daten des einlösenden Antragstellers für das Log-Archiv.
     *
     * @return array<string, mixed>|null Die Gutschein-Konfigurationsdaten bei Erfolg, andernfalls null.
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

        if ($arcCfg['type'] === 'mysql' && $this->pdo instanceof \PDO) {
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
            $archivePath = $this->config->get('root_path') . '/' .
                $this->config->get('storage_path_prefix') . $arcCfg['file'];
            $archive = \file_exists($archivePath) ? \json_decode(
                (string) \file_get_contents($archivePath),
                true,
            ) : [];
            $archive[] = $archiveEntry;
            \file_put_contents(
                $archivePath,
                \json_encode($archive, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
            );
        }
        // --- ENDE ARCHIV-LOGIK ---

        // Lösch-Logik für den aktiven Gutschein
        $shouldDelete = ! ($voucher['multi_use'] ?? false);
        if (
            ($voucher['multi_use'] ?? false)
            && (int) $voucher['max_uses'] > 0
            && $voucher['uses_count'] >= $voucher['max_uses']
        ) {
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
     * Lädt alle aktiven, einlösbaren Gutscheine aus dem konfigurierten Repository (MySQL oder JSON).
     *
     * @return array<string, array<string, mixed>> Assoziatives Array aller Gutscheine, indiziert nach Code.
     */
    public function loadVouchers(): array
    {
        $cfg = $this->config->get('storage_config')['vouchers'];

        if ($cfg['type'] === 'mysql') {
            if (!$this->pdo instanceof \PDO) {
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
     * Persistiert den vollständigen Gutschein-Livebestand im aktiven Speicher-Backend.
     *
     * @param array<string, array<string, mixed>> $vouchers Die zu speichernde Gutscheinliste.
     */
    public function saveVouchers(array $vouchers): void
    {
        $cfg = $this->config->get('storage_config')['vouchers'];
        if ($cfg['type'] === 'mysql') {
            $this->pdo->exec("DELETE FROM {$cfg['table']}");

            // Neues SQL-Statement inkl. status
            $sql = "INSERT INTO {$cfg['table']} (
                code, reason, template_key, type, value, multi_use, max_uses,
                uses_count, expires_at, date_mode, created_by, created_at, status, data
            ) VALUES (
                :code, :reason, :template_key, :type, :value, :multi_use, :max_uses,
                :uses_count, :expires_at, :date_mode, :created_by, :created_at, :status, :data
            )";

            $stmt = $this->pdo->prepare($sql);

            foreach ($vouchers as $v) {
                // Explizites Mapping schützt vor HY093 und fehlenden Keys
                $stmt->execute([
                    'code'         => $v['code'] ?? '',
                    'reason'       => $v['reason'] ?? '',
                    'template_key' => $v['template_key'] ?? 'std_7',
                    'type'         => $v['type'] ?? 'free',
                    'value'        => (float) ($v['value'] ?? 0.0),
                    'multi_use'    => (int) ($v['multi_use'] ?? 0),
                    'max_uses'     => (int) ($v['max_uses'] ?? 1),
                    'uses_count'   => (int) ($v['uses_count'] ?? 0),
                    'expires_at'   => $v['expires_at'] ?? null,
                    'date_mode'    => $v['date_mode'] ?? 'fixed',
                    'created_by'   => $v['created_by'] ?? '',
                    'created_at'   => $v['created_at'] ?? \date('Y-m-d H:i:s'),
                    'status'       => $v['status'] ?? 'aktiv',
                    'data'         => \json_encode($v['data'] ?? [], \JSON_UNESCAPED_UNICODE),
                ]);
            }

            return;
        }

        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
        \file_put_contents($path, \json_encode($vouchers, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Deaktiviert (Sperrt) einen aktiven Gutschein vorzeitig unter Angabe einer Begründung.
     *
     * @param string $code   Der zu sperrende Code.
     * @param string $reason Die administrative Begründung der Sperrung.
     *
     * @return bool True bei erfolgreicher Sperrung.
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
     * Lädt alle historischen Protokolle bereits verbrauchter/eingelöster Gutscheine aus dem Archiv.
     *
     * @return array<int, array<string, mixed>> Zeitlich absteigend sortierte Liste der Einlösungs-Logs.
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
     * Universelle Methode zum schnellen Umschalten des Gutschein-Status (z.B. 'aktiv', 'deaktiviert').
     *
     * @param string $code   Der Ziel-Code.
     * @param string $status Der neue Statusname.
     *
     * @return bool True bei Erfolg.
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

    /**
     * Validiert die formale Verwendbarkeit eines Gutscheins.
     * Prüft das logische Lösch-Flag, evaluiert Ablaufdaten gegen die aktuelle Systemzeit
     * und gleicht den Nutzungszähler (`uses_count`) gegen das konfigurierte Limit ab.
     *
     * @param array<string, mixed> $voucher Der zu validierende Gutschein-Datensatz.
     *
     * @return bool True, wenn der Gutschein aktuell uneingeschränkt einlösbar ist.
     */
    public function isValid(array $voucher): bool
    {
        // 1. Check: Administrativ deaktiviert?
        if (($voucher['status'] ?? 'aktiv') === 'deaktiviert') {
            return false;
        }

        // 2. Check: Ablaufdatum überschritten?
        if (! empty($voucher['expires_at'])) {
            try {
                $expiry = new \DateTimeImmutable($voucher['expires_at']);
                if ($expiry < new \DateTimeImmutable()) {
                    return false;
                }
            } catch (\Exception) {
                // Bei korruptem Datumsformat lieber ungültig
                return false;
            }
        }

        // 3. Check: Nutzungslimit erreicht? (Zusatz-Sicherheit für die Anzeige)
        $multi = (bool) ($voucher['multi_use'] ?? false);
        $max   = (int) ($voucher['max_uses'] ?? 1);
        $count = (int) ($voucher['uses_count'] ?? 0);

        return ! $multi || $max <= 0 || $count < $max;
    }
}
