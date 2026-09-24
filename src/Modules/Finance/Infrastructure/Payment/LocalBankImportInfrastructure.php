<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Payment;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Finance\Application\Contracts\BankImportInfrastructureInterface;
use Exception;
use League\Csv\Reader;
use Override;
use ZipArchive;

/**
 * Physische Implementierung der Dateioperationen für den Bank-Import.
 * Kapselt file_get_contents, fopen und ZipArchive sicher ab.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class LocalBankImportInfrastructure implements BankImportInfrastructureInterface
{
    public function __construct(
        private ConfigInterface $config,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function normalizeAndOpenCsv(string $filePath): ?Reader
    {
        if (!\file_exists($filePath)) {
            return null;
        }

        $content = \file_get_contents($filePath);
        if (!\is_string($content) || $content === '') {
            return null;
        }

        if (\str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = \substr($content, 3);
        }

        $encoding = \mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-15', 'ISO-8859-1', 'ASCII'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = \mb_convert_encoding($content, 'UTF-8', $encoding);
        } elseif (!$encoding) {
            $content = \mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $content = \str_replace(["\r\n", "\r"], "\n", $content);
        \file_put_contents($filePath, $content);

        $stream = @\fopen($filePath, 'r');
        if ($stream === false) {
            return null;
        }

        try {
            $csv = Reader::from($stream);
            $csv->setDelimiter($this->detectDelimiter($filePath));
            $csv->setHeaderOffset(null);

            return $csv;
        } catch (Exception) {
            return null;
        }
    }

    private function detectDelimiter(string $filePath): string
    {
        $handle = @\fopen($filePath, 'r');
        if ($handle === false) {
            return ';';
        }

        $firstLine = \fgets($handle);
        \fclose($handle);

        if ($firstLine === false) {
            return ';';
        }

        $delimiters = [
            ';' => \substr_count($firstLine, ';'),             ',' => \substr_count($firstLine, ','),
            "\t" => \substr_count($firstLine, "\t"),
            '|' => \substr_count($firstLine, '|'),         ];
        \arsort($delimiters);

        return (string) \array_key_first($delimiters);
    }

    #[Override]
    public function writeLog(string $message, array &$runLogs): void
    {
        $logDir = \rtrim((string) $this->config->get('root_path', ''), '/\\') . '/logs';
        if (!\is_dir($logDir)) {
            @\mkdir($logDir, 0o755, true);
        }

        $logFile = $logDir . '/bank_import.log';
        $timestamp = $this->clock->now()->format('d-M-Y H:i:s e');
        $formattedMessage = "[$timestamp] BankImport:$message\n";

        @\file_put_contents($logFile, $formattedMessage, \FILE_APPEND | \LOCK_EX);
        $runLogs[] = $formattedMessage;
    }

    #[Override]
    public function createArchiveZip(string $csvFilePath, array $logs): void
    {
        $root = \rtrim((string) $this->config->get('root_path', ''), '/\\');
        $archiveDir = $root . '/storage/bank_imports';

        if (!\is_dir($archiveDir)) {
            @\mkdir($archiveDir, 0o755, true);
        }

        $htaccessPath = $archiveDir . '/.htaccess';
        if (!\file_exists($htaccessPath)) {
            @\file_put_contents($htaccessPath, "Order allow,deny\nDeny from all\n");
        }

        $timestamp = $this->clock->now()->format('Ymd_His');
        $zipFilename = $archiveDir . '/import_' . $timestamp . '_' . \bin2hex(\random_bytes(8)) . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return;
        }

        $zip->addFile($csvFilePath, 'import_' . $timestamp . '.csv');
        $zip->addFromString('import_' . $timestamp . '.log', \implode('', $logs));

        $password = (string) $this->config->get('bank_import_zip_password', '');
        if ($password !== '') {
            $zip->setPassword($password);
            $zip->setEncryptionName('import_' . $timestamp . '.csv', ZipArchive::EM_AES_256);
            $zip->setEncryptionName('import_' . $timestamp . '.log', ZipArchive::EM_AES_256);
        }

        $zip->close();
    }

    #[Override]
    public function cleanupTempFile(string $filePath): void
    {
        @\unlink($filePath);
    }
}
