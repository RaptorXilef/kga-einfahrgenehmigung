<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

/**
 * Interface für das Speicher-Repository der E-Mail-Warteschlange.
 * Verwaltet das Einreihen und die Batch-Verarbeitung von E-Mails zur Entlastung des Requests.
 *
 * Path: src/Contracts/Storage/MailQueueRepositoryInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface MailQueueRepositoryInterface
{
    /**
     * Reiht eine neue E-Mail in die Warteschlange ein.
     *
     * @param string               $recipient E-Mail-Adresse des Empfängers.
     * @param string               $subject   Betreff der E-Mail.
     * @param string               $template  Template-Bezeichner.
     * @param array<string, mixed> $data      Template-Variablen.
     */
    public function enqueue(string $recipient, string $subject, string $template, array $data): void;

    /**
     * Verarbeitet einen Stapel von ausstehenden E-Mails.
     *
     * @param int      $limit     Maximale Anzahl an E-Mails pro Durchlauf.
     * @param callable $processor Callback-Funktion zur eigentlichen Verarbeitung.
     *
     * @return int Anzahl der erfolgreich versendeten E-Mails.
     */
    public function processBatch(int $limit, callable $processor): int;
}
