<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\MailJob;

/**
 * Interface für das Speicher-Repository der E-Mail-Warteschlange.
 * Verwaltet das Einreihen und die Batch-Verarbeitung von E-Mails zur Entlastung des Requests.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface MailQueueRepositoryInterface
{
    public function enqueue(MailJob $job): void;

    /**
     * Verarbeitet einen Stapel von ausstehenden E-Mails.
     *
     * @param int      $limit     Maximale Anzahl an E-Mails pro Durchlauf.
     * @param callable $processor Callback-Funktion zur eigentlichen Verarbeitung.
     *
     * @return int Anzahl der erfolgreich versendeten E-Mails.
     */
    public function processBatch(int $limit, callable $processor): int;

    public function import(array $data): void;
}
