<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\VerificationRequest;

/**
 * Interface für das Speicher-Repository des Double-Opt-In Warteraums.
 * Trennt unbestätigte E-Mails von verifizierten, aber noch unbezahlten Anträgen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface VerificationRepositoryInterface
{
    /**
     * Lädt alle unbestätigten Anträge (E-Mail noch nicht bestätigt).
     *
     * @return VerificationRequest[]
     */
    public function loadPending(): array;

    /**
     * Speichert Anträge, die noch auf E-Mail-Bestätigung warten.
     *
     * @param VerificationRequest[] $data
     * @param bool                  $forceSql Erzwingt das Speichern in MySQL (ignoriert JSON).
     */
    public function savePending(array $data, bool $forceSql = false): void;

    /**
     * Lädt Anträge, deren E-Mail bestätigt ist, die aber noch auf Zahlung warten.
     *
     * @return VerificationRequest[]
     */
    public function loadVerified(): array;

    /**
     * Speichert Anträge, die auf Zahlung warten.
     *
     * @param VerificationRequest[] $data
     * @param bool                  $forceSql Erzwingt das Speichern in MySQL (ignoriert JSON).
     */
    public function saveVerified(array $data, bool $forceSql = false): void;

    public function import(array $data): void;
}
