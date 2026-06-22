<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Contracts\Utils\ClockInterface;
use App\Core\Entity\Voucher;

/**
 * Service zur Verwaltung von Gutscheincodes und Rabatten.
 * Übernimmt die Erstellung, Einlösung und Validierung von Codes.
 *
 * Erzeugt fälschungssichere Gutscheincodes, unterstützt Mehrfachnutzung ('multi_use'),
 * Verfallsdaten, Vorbefüllungs-Schablonen für Anträge und protokolliert Einlösungen revisionssicher im Archiv.
 * Kontext: Marketing- und Administrations-Subkomponente für das Gutscheinwesen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VoucherService
{
    public function __construct(
        private ClockInterface $clock,
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
            do {
                // Schleife: Generiere so lange neu, bis der Code wirklich einmalig ist
                // Entropie auf 8 Bytes (16 Zeichen) erhöht für maximale Vorhersageresistenz
                $newGeneratedCode = 'GUT-' . \strtoupper(\bin2hex(\random_bytes(8)));
            } while (\in_array($newGeneratedCode, $alreadyUsedCodes, true));
        }

        $voucher = new Voucher(
            $newGeneratedCode,
            $reason,
            $template_key,
            $type,
            $value,
            $multi_use,
            $max_uses ?? 1,
            0,
            $expires_at ? new \DateTimeImmutable($expires_at) : null,
            $date_mode,
            $created_by,
            clone $this->clock->now(),
            'aktiv',
            $prefillData,
        );

        $activeVouchers[$newGeneratedCode] = $voucher;
        $this->repository->saveAll($activeVouchers);

        return $newGeneratedCode;
    }

    /**
     * Schritt 2: Gültigkeit zur Laufzeit im Beantragungsformular checken
     *
     * Prüft, ob ein gegebener Gutschein aktuell gültig ist (Verfallsdatum, Status & Nutzungen).
     *
     * @return bool True, wenn der Gutschein noch eingelöst werden kann.
     */
    public function isValid(Voucher $voucher): bool
    {
        return $voucher->isValid($this->clock->now());
    }

    /**
     * Schritt 3: Code einlösen, Zähler hochsetzen, ins Archiv loggen
     *
     * Löst einen Gutschein ein, inkrementiert den Zähler und verschiebt ihn bei Erschöpfung ins Archiv.
     */
    public function useVoucher(string $code, array $userData = []): ?Voucher
    {
        $vouchers = $this->repository->loadAll();
        if (! isset($vouchers[$code])) {
            return null;
        }

        $voucher  = $vouchers[$code];
        $redeemed = $voucher->redeem();

        // --- ARCHIV-LOGIK VIA CONFIG ---
        $this->repository->appendToArchive([
            'code'        => $code,
            'reason'      => $redeemed->reason,
            'template'    => $redeemed->templateKey,
            'redeemed_at' => $this->clock->nowAsString(),
            'user_name'   => $userData['name'] ?? 'Unbekannt',
            'user_plot'   => $userData['parzelle'] ?? '?',
            'user_email'  => $userData['email'] ?? '?',
        ]);

        if (! $redeemed->multiUse || $redeemed->isDepleted()) {
            unset($vouchers[$code]);
        } else {
            $vouchers[$code] = $redeemed;
        }

        $this->repository->saveAll($vouchers);

        return $redeemed;
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

        $vouchers[$code] = $vouchers[$code]->withStatus($status);
        $this->repository->saveAll($vouchers);

        return true;
    }

    /**
     * TODO DOCBLOCK
     * Löscht einen Gutschein unwiderruflich aus dem System.
     */
    public function deleteVoucher(string $code): bool
    {
        $vouchers = $this->repository->loadAll();
        if (! isset($vouchers[$code])) {
            return false;
        }

        unset($vouchers[$code]);
        $this->repository->saveAll($vouchers);

        return true;
    }
}
