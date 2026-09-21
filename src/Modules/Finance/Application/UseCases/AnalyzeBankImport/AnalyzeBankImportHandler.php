<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\AnalyzeBankImport;

use App\SharedKernel\Application\Query\QueryHandlerInterface;
use Exception;
use League\Csv\Reader;

/**
 * @implements QueryHandlerInterface<AnalyzeBankImportQuery, BankImportAnalysisDto>
 */
final readonly class AnalyzeBankImportHandler implements QueryHandlerInterface
{
    /**
     * @param AnalyzeBankImportQuery $query
     */
    public function handle(mixed $query): BankImportAnalysisDto
    {
        if (!\file_exists($query->tempFilePath)) {
            return new BankImportAnalysisDto([], []);
        }

        $this->prepareAndNormalizeFile($query->tempFilePath);

        try {
            $stream = \fopen($query->tempFilePath, 'r');
            if ($stream === false) {
                return new BankImportAnalysisDto([], []);
            }

            $csv = Reader::from($stream);
            $csv->setDelimiter($this->detectDelimiter($query->tempFilePath));
            $csv->setHeaderOffset(null);

            $iterator = $csv->getIterator();
            $iterator->rewind();

            $headers = $iterator->valid() ? $iterator->current() : [];
            $iterator->next();
            $previewRow = $iterator->valid() ? $iterator->current() : [];

            return new BankImportAnalysisDto(
                \is_array($headers) ? $headers : [],
                \is_array($previewRow) ? $previewRow : [],
            );
        } catch (Exception $e) {
            \error_log('AnalyzeBankImportHandler Error: ' . $e->getMessage());

            return new BankImportAnalysisDto([], []);
        }
    }

    private function prepareAndNormalizeFile(string $filePath): void
    {
        $content = \file_get_contents($filePath);
        if (!\is_string($content) || $content === '') {
            return;
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
    }

    private function detectDelimiter(string $filePath): string
    {
        $handle = \fopen($filePath, 'r');
        if ($handle === false) {
            return ';';
        }
        $firstLine = \fgets($handle);
        \fclose($handle);
        if ($firstLine === false) {
            return ';';
        }
        $delimiters = [
            ';' => \substr_count($firstLine, ';'),
            ',' => \substr_count($firstLine, ','),
            "\t" => \substr_count($firstLine, "\t"),
            '|' => \substr_count($firstLine, '|'),
        ];
        \arsort($delimiters);

        return (string) \array_key_first($delimiters);
    }
}
