<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Storage\VoucherRepositoryInterface;

/**
 * Service zur Verwaltung von Gutscheincodes und Rabatten.
 * Übernimmt die Erstellung, Einlösung und Validierung von Codes.
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

    // --- Voucher Lifecycle API ---

    /**
     * Schritt 1: Code generieren und Sperrlisten prüfen
     *
     * Generiert einen neuen Gutschein im System mit individuellen Restriktionen.
     * Überprüft Wunsch-Codes auf Einzigartigkeit gegen Live- und Archivbestände oder generiert ein 'GUT-'-Krypto-Token.
     *
     * @param string      $reason       Der Grund oder Verwendungszweck des Gutscheins.
     * @param string      $created_by   Kennung des Erstellers.
     * @param string      $template_key Der zugehörige Vorlagen-Schlüssel.
     * @param array       $prefillData  Optionale Formulardaten zum Vorbefüllen.
     * @param string      $type         Die Art des Gutscheins ('free', 'percent', 'fixed').
     * @param float       $value        Der Wert des Gutscheins (z.B. Prozent oder fester Euro-Betrag).
     * @param bool        $multi_use    Gibt an, ob der Gutschein mehrfach verwendet werden kann.
     * @param int|null    $max_uses     Maximale Anzahl der Verwendungen (null = unbegrenzt).
     * @param string|null $custom_code  Ein benutzerdefinierter Code (optional).
     * @param string|null $expires_at   Verfallsdatum (YYYY-MM-DD HH:MM:SS) oder null.
     * @param string      $date_mode    Der Modus der Datumsberechnung ('fixed' etc.).
     *
     * @return string Der generierte oder übergebene Gutscheincode.
     *
     * @throws \RuntimeException Wenn ein benutzerdefinierter Code bereits existiert.
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
     * Schritt 2: Gültigkeit zur Laufzeit im Beantragungsformular checken
     *
     * Prüft, ob ein gegebener Gutschein aktuell gültig ist (Verfallsdatum, Status & Nutzungen).
     *
     * @param array<string, mixed> $voucher Das Gutschein-Daten-Array.
     *
     * @return bool True, wenn der Gutschein noch eingelöst werden kann.
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

    /**
     * Schritt 3: Code einlösen, Zähler hochsetzen, ins Archiv loggen
     *
     * Löst einen Gutschein ein, inkrementiert den Zähler und verschiebt ihn bei Erschöpfung ins Archiv.
     *
     * @param string               $code     Der einzulösende Gutscheincode.
     * @param array<string, mixed> $userData Daten des Nutzers für das Archiv (Name, Parzelle, E-Mail).
     *
     * @return array<string, mixed>|null Die Daten des Gutscheins bei Erfolg, sonst null.
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

    // --- Administrative Management ---

    /**
     * Gutschein manuell deaktivieren/reaktivieren
     *
     * Ändert den Status eines Gutscheins.
     *
     * @param string $code   Der Gutscheincode.
     * @param string $status Der neue Status ('aktiv' oder 'deaktiviert').
     *
     * @return bool True bei Erfolg, false wenn der Code nicht gefunden wurde.
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
     * Gutschein vorzeitig mit Begründung sperren
     *
     * Deaktiviert (Sperrt) einen aktiven Gutschein vorzeitig unter Angabe einer Begründung.
     *
     * @param string $code   Der zu deaktivierende Gutscheincode.
     * @param string $reason Die Begründung für die Deaktivierung.
     *
     * @return bool True bei Erfolg, false wenn der Code nicht gefunden wurde.
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
}
