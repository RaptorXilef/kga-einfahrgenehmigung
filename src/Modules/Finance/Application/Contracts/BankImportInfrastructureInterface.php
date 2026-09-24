<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Contracts;

use League\Csv\Reader;

/**
 * Entkoppelt die Application-Schicht von nativen Dateisystem- und I/O-Funktionen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface BankImportInfrastructureInterface
{
    public function storeTempFile(string $tmpName): string;

    public function normalizeAndOpenCsv(string $filePath): ?Reader;

    public function writeLog(string $message, array &$runLogs): void;

    public function createArchiveZip(string $csvFilePath, array $logs): void;

    public function cleanupTempFile(string $filePath): void;
}
