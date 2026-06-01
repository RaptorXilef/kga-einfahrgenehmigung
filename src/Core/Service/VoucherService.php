<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Storage\VoucherRepositoryInterface;

/**
 * TODO Phase 3 Bearbeitet
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
        private VoucherRepositoryInterface $repository,
    ) {
    }

    /**
     * Generiert einen neuen Gutschein im System mit individuellen Restriktionen.
     * Überprüft Wunsch-Codes auf Einzigartigkeit gegen Live- und Archivbestände oder generiert ein 'GUT-'-Krypto-Token.
     *
     * @param string               $reason       Der Ausstellungsgrund (z.B. "Vorstandsentlastung").
     * @param string               $created_by   Die ID oder der Name des ausstellenden Administrators.
     * @param string               $template_key Das verknüpfte Tarif-Template (z.B. 'std_7').
     * @param array<string, mixed> $prefillData  Optionale Stammdaten zur Zwangs-Vorbefüllung des Formulars.
     * @param string               $type         Der Rabatt-Typ ('free', 'fixed', 'percent').
     * @param float                $value        Der numerische Rabattwert (Betrag oder Prozentsatz).
     * @param bool                 $multi_use    True, wenn der Gutschein von mehreren Personen genutzt werden darf.
     * @param int|null             $max_uses     Maximale Einlösungsanzahl bei Multi-Use.
     * @param string|null          $custom_code  Optionaler Wunsch-Code (z.B. "SOMMER2026").
     * @param string|null          $expires_at   Optionales Ablaufdatum (Y-m-d).
     * @param string               $date_mode    Gültigkeitsmodus für Termine ('fixed' oder flexibel).
     *
     * @return string Der finale, registrierte Gutscheincode im System.
     */
    public function createVoucher(
        string $reason,
        string $created_by,
        string $template_key,
        array $prefillData = [],
        string $type = 'free', // free, fixed, percent
        float $value = 0.0,    // Betrag oder Prozent
        bool $multi_use = false,
        ?int $max_uses = 1,
        ?string $custom_code = null, // Optionaler individueller Code
        ?string $expires_at = null,
        string $date_mode = 'fixed',  // 'fixed' oder 'flexible'
    ): string {
        $activeVouchers = $this->repository->loadAll();
        $archivedItems  = $this->repository->loadArchive(); // Hier wird die Datei der benutzten Codes geladen!

        // Wir sammeln alle bereits vergebenen Codes in einer Liste für den Abgleich
        $alreadyUsedCodes = \array_keys($activeVouchers);
        foreach ($archivedItems as $archivedEntry) {
            $alreadyUsedCodes[] = $archivedEntry['code']; // Füge benutzte Codes zur Sperrliste hinzu
        }

        // Logik für Code-Findung
        if ($custom_code !== null && \trim($custom_code) !== '') {
            $newGeneratedCode = \strtoupper(\trim($custom_code));
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
            'multi_use'    => $multi_use,
            'max_uses'     => $max_uses,
            'uses_count'   => 0,
            'expires_at'   => $expires_at,
            'date_mode'    => $date_mode,
            'data'         => $prefillData,
            'created_by'   => $created_by,
            'created_at'   => \date('Y-m-d H:i:s'),
        ];

        $this->repository->saveAll($activeVouchers);

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
        $vouchers = $this->repository->loadAll();
        if (! isset($vouchers[$code])) {
            return null;
        }

        $voucher = &$vouchers[$code];
        ++$voucher['uses_count'];

        // --- ARCHIV-LOGIK VIA CONFIG ---
        $this->repository->appendToArchive([
            'code'        => $code,
            'reason'      => $voucher['reason'],
            'template'    => $voucher['template_key'],
            'redeemed_at' => \date('Y-m-d H:i:s'),
            'user_name'   => $userData['name'] ?? 'Unbekannt',
            'user_plot'   => $userData['parzelle'] ?? '?',
            'user_email'  => $userData['email'] ?? '?',
        ]);

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

        $this->repository->saveAll($vouchers);

        return $voucher;
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
        $vouchers = $this->repository->loadAll();
        if (! isset($vouchers[$code])) {
            return false;
        }

        $vouchers[$code]['status'] = 'deaktiviert';
        $vouchers[$code]['note']   = $reason;

        $this->repository->saveAll($vouchers);

        return true;
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
        $vouchers = $this->repository->loadAll();
        if (! isset($vouchers[$code])) {
            return false;
        }

        $vouchers[$code]['status'] = $status;
        $this->repository->saveAll($vouchers);

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

    // Die folgenden Methoden leiten wir vorübergehend durch, damit Controller (die noch darauf zugreifen) nicht kaputt gehen
    public function loadVouchers(): array
    {
        return $this->repository->loadAll();
    }
}
