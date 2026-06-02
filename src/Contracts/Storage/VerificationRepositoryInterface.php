<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

/**
 * Interface für das Speicher-Repository des Double-Opt-In Warteraums.
 * Trennt unbestätigte E-Mails von verifizierten, aber noch unbezahlten Anträgen.
 *
 * Path: src/Contracts/Storage/VerificationRepositoryInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface VerificationRepositoryInterface
{
    /**
     * Lädt alle unbestätigten Anträge (E-Mail noch nicht bestätigt).
     *
     * @return array<string, array<string, mixed>> Anträge im Pending-Status.
     */
    public function loadPending(): array;

    /**
     * Speichert Anträge, die noch auf E-Mail-Bestätigung warten.
     *
     * @param array<string, array<string, mixed>> $data     Die zu speichernden Anträge.
     * @param bool                                $forceSql Erzwingt das Speichern in MySQL (ignoriert JSON).
     */
    public function savePending(array $data, bool $forceSql = false): void;

    /**
     * Lädt Anträge, deren E-Mail bestätigt ist, die aber noch auf Zahlung warten.
     *
     * @return array<string, array<string, mixed>> Anträge im Verified-Status.
     */
    public function loadVerified(): array;

    /**
     * Speichert Anträge, die auf Zahlung warten.
     *
     * @param array<string, array<string, mixed>> $data     Die zu speichernden Anträge.
     * @param bool                                $forceSql Erzwingt das Speichern in MySQL (ignoriert JSON).
     */
    public function saveVerified(array $data, bool $forceSql = false): void;
}
