<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ProcessBankImport;

/**
 * Mutabler State-Bag für den Bank-Import-Prozess.
 */
final class ProcessBankImportContext
{
    public bool $success = false;

    public string $message = '';

    public int $successCount = 0;

    public int $skippedCount = 0;

    public int $errorCount = 0;

    public array $successDetails = [];

    public array $skippedDetails = [];

    public array $errorDetails = [];

    public array $collectiveTransfers = [];
}
